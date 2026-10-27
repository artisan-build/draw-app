<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BoardShareKind;
use App\Enums\ShareAccess;
use App\Services\BoardAccess;
use App\Services\BoardShareService;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BoardShareController extends Controller
{
    public function store(
        Request $request,
        string $board,
        BoardAccess $access,
        BoardShareService $shares,
    ): RedirectResponse {
        $actor = $this->user($request);
        $boardModel = $access->findOwned($actor, $board);

        /** @var array{kind: string, recipient_user_id?: int|null, access?: string|null, expires_in_minutes?: int|string|null} $validated */
        $validated = $request->validate([
            'kind' => ['required', Rule::enum(BoardShareKind::class)],
            'recipient_user_id' => [
                'nullable',
                'required_if:kind,user',
                'prohibited_unless:kind,user',
                'integer',
                Rule::exists(User::class, 'id')->where(fn ($query) => $query->where('status', 'active')),
                Rule::notIn([$actor->id]),
            ],
            'access' => [
                'nullable',
                'prohibited_if:kind,link',
                Rule::enum(ShareAccess::class),
            ],
            'expires_in_minutes' => [
                'nullable',
                'prohibited_unless:kind,link',
                'integer',
                'min:1',
                'max:525600',
            ],
        ]);

        $kind = BoardShareKind::from($validated['kind']);
        $shareAccess = isset($validated['access']) ? ShareAccess::from($validated['access']) : null;
        $expiresAt = isset($validated['expires_in_minutes'])
            ? now()->addMinutes((int) $validated['expires_in_minutes'])
            : null;

        $shares->create(
            $actor,
            $boardModel,
            $kind,
            isset($validated['recipient_user_id']) ? (int) $validated['recipient_user_id'] : null,
            $shareAccess,
            $expiresAt,
        );

        return to_route('board-shares.index');
    }

    public function destroy(
        Request $request,
        string $share,
        BoardAccess $access,
        BoardShareService $shares,
    ): RedirectResponse {
        $actor = $this->user($request);
        $shareModel = $access->findOwnedShare($actor, $share);
        $shares->delete($actor, $shareModel);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

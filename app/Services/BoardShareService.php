<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BoardShareKind;
use App\Enums\ShareAccess;
use App\Models\Board;
use App\Models\BoardShare;
use ArtisanBuild\BuiltForCloud\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class BoardShareService
{
    public function create(
        User $actor,
        Board $board,
        BoardShareKind $kind,
        ?int $recipientUserId = null,
        ?ShareAccess $access = null,
        ?CarbonInterface $expiresAt = null,
    ): BoardShare {
        if ($kind === BoardShareKind::Link && ($recipientUserId !== null || $access !== null)) {
            throw new LogicException('Signed links cannot carry a recipient or access level.');
        }

        if ($kind === BoardShareKind::User && $recipientUserId === null) {
            throw new LogicException('Direct shares require a recipient.');
        }

        if ($kind === BoardShareKind::Team && $recipientUserId !== null) {
            throw new LogicException('Team shares cannot carry a recipient.');
        }

        if ($kind !== BoardShareKind::Link && $expiresAt !== null) {
            throw new LogicException('Only signed links may expire.');
        }

        return DB::transaction(function () use ($actor, $board, $kind, $recipientUserId, $access, $expiresAt): BoardShare {
            $locked = Board::query()->lockForUpdate()->whereKey($board->id)->firstOrFail();
            abort_unless($locked->creator_id === $actor->id, 404);

            return $locked->shares()->create([
                'kind' => $kind,
                'recipient_user_id' => $recipientUserId,
                'access' => $kind === BoardShareKind::Link ? null : ($access ?? ShareAccess::View),
                'expires_at' => $kind === BoardShareKind::Link ? $expiresAt : null,
            ]);
        });
    }

    public function delete(User $actor, BoardShare $share): void
    {
        $boardId = $share->board_id;

        DB::transaction(function () use ($actor, $boardId, $share): void {
            $board = Board::query()->lockForUpdate()->whereKey($boardId)->firstOrFail();
            abort_unless($board->creator_id === $actor->id, 404);

            BoardShare::query()
                ->whereKey($share->id)
                ->where('board_id', $board->id)
                ->firstOrFail()
                ->delete();
        });
    }
}

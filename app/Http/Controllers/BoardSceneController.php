<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Board;
use App\Services\BoardAccess;
use App\Services\BoardSceneService;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardSceneController extends Controller
{
    public function show(Request $request, string $board, BoardAccess $access): JsonResponse
    {
        return $this->response($access->findViewable($this->user($request), $board));
    }

    public function update(
        Request $request,
        string $board,
        BoardAccess $access,
        BoardSceneService $scenes,
    ): JsonResponse {
        $actor = $this->user($request);
        $boardModel = $access->findEditable($actor, $board);

        /** @var array{revision: int, elements: array<int, array<string, mixed>>, appState?: array<string, mixed>, files?: array<string, mixed>} $validated */
        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'elements' => ['present', 'array'],
            'elements.*' => ['array'],
            'appState' => ['sometimes', 'array'],
            'files' => ['sometimes', 'array'],
        ]);

        return $this->response($scenes->saveEditorScene($actor, $boardModel, $validated, $validated['revision']));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function response(Board $board): JsonResponse
    {
        return response()->json([
            'scene' => $board->scene,
            'revision' => $board->revision,
            'updatedAt' => $board->updated_at?->toISOString(),
        ]);
    }
}

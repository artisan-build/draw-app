<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BoardShareKind;
use App\Models\Board;
use App\Models\BoardShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicBoardShareController extends Controller
{
    public function show(Request $request, string $share): View
    {
        $shareModel = $this->validLink($request, $share);

        return view('board-shares.public', [
            'board' => $shareModel->board,
            'share' => $shareModel,
            'sceneUrl' => $shareModel->signedUrl('board-shares.public.scene'),
        ]);
    }

    public function scene(Request $request, string $share): JsonResponse
    {
        return $this->sceneResponse($this->validLink($request, $share)->board);
    }

    private function validLink(Request $request, string $share): BoardShare
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['frame']), 404);

        $shareModel = BoardShare::query()->with('board')->whereKey($share)->firstOrFail();
        abort_unless(
            $shareModel->kind === BoardShareKind::Link
                && ($shareModel->expires_at === null || $shareModel->expires_at->isFuture()),
            404,
        );

        return $shareModel;
    }

    private function sceneResponse(Board $board): JsonResponse
    {
        return response()->json([
            'scene' => $board->scene,
            'revision' => $board->revision,
            'updatedAt' => $board->updated_at?->toISOString(),
        ]);
    }
}

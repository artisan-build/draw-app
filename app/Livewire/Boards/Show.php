<?php

declare(strict_types=1);

namespace App\Livewire\Boards;

use App\Enums\BoardShareKind;
use App\Models\BoardShare;
use App\Services\BoardAccess;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public string $boardId;

    public function mount(string $board, BoardAccess $access): void
    {
        $this->boardId = $access->findViewable($this->user(), $board)->id;
    }

    public function render(BoardAccess $access): View
    {
        $board = $access->findViewable($this->user(), $this->boardId);
        $canEdit = $access->editableBy($this->user())->whereKey($board->id)->exists();
        $canShare = $board->creator_id === $this->user()->id;
        $newShareId = session('new_board_share_id');
        $newLink = $canShare && is_string($newShareId)
            ? BoardShare::query()
                ->whereKey($newShareId)
                ->where('board_id', $board->id)
                ->where('kind', BoardShareKind::Link)
                ->first()
            : null;

        return view('livewire.boards.show', [
            'board' => $board,
            'canEdit' => $canEdit,
            'canShare' => $canShare,
            'agentPrompt' => sprintf(
                'Use the Scalpels MCP to work on the Draw board “%s” (board ID %s). Call `installed_apps` and choose the installed Draw app. Call `app_tools`, then use `get_board` and `add_elements` on board `%s`. Then ask me what to draw',
                $board->title,
                $board->id,
                $board->id,
            ),
            'shareCreated' => session()->has('board_share_created'),
            'newShareUrl' => $newLink?->signedUrl(),
            'users' => $canShare
                ? User::query()
                    ->whereKeyNot($this->user()->id)
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get(['id', 'name', 'email'])
                : collect(),
        ])->title($board->title);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

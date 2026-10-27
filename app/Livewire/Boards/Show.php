<?php

declare(strict_types=1);

namespace App\Livewire\Boards;

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

        return view('livewire.boards.show', [
            'board' => $board,
        ])->title($board->title);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

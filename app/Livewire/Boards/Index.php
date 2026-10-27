<?php

declare(strict_types=1);

namespace App\Livewire\Boards;

use App\Models\Board;
use App\Services\BoardAccess;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Index extends Component
{
    public string $title = '';

    public function create(): RedirectResponse
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        $board = Board::query()->create([
            'creator_id' => $this->user()->id,
            'title' => $validated['title'],
        ]);

        return to_route('boards.show', ['board' => $board->id]);
    }

    public function render(BoardAccess $access): View
    {
        return view('livewire.boards.index', [
            'boards' => $access->ownedBy($this->user())->latest('updated_at')->get(),
        ])->title('Boards');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

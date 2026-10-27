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
class SharedWithMe extends Component
{
    public function render(BoardAccess $access): View
    {
        return view('livewire.boards.shared-with-me', [
            'boards' => $access->sharedWith($this->user())->latest('updated_at')->get(),
        ])->title('Shared with me');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

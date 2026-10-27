<?php

declare(strict_types=1);

namespace App\Livewire\Shares;

use App\Models\BoardShare;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Index extends Component
{
    public function render(): View
    {
        return view('livewire.shares.index', [
            'shares' => BoardShare::query()
                ->whereHas('board', fn (Builder $query): Builder => $query->where('creator_id', $this->user()->id))
                ->with(['board:id,title,creator_id', 'recipient:id,name,email'])
                ->latest()
                ->get(),
        ])->title('My shares');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

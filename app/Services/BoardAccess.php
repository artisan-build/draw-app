<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Board;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Database\Eloquent\Builder;

class BoardAccess
{
    /** @return Builder<Board> */
    public function viewableBy(User $user): Builder
    {
        return Board::query()->where('creator_id', $user->id);
    }

    /** @return Builder<Board> */
    public function editableBy(User $user): Builder
    {
        return Board::query()->where('creator_id', $user->id);
    }

    public function findViewable(User $user, string $id): Board
    {
        return $this->viewableBy($user)->whereKey($id)->firstOrFail();
    }

    public function findEditable(User $user, string $id): Board
    {
        return $this->editableBy($user)->whereKey($id)->firstOrFail();
    }
}

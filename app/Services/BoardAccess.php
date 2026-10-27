<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BoardShareKind;
use App\Enums\ShareAccess;
use App\Models\Board;
use App\Models\BoardShare;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Database\Eloquent\Builder;

class BoardAccess
{
    /** @return Builder<Board> */
    public function viewableBy(User $user): Builder
    {
        return Board::query()->where(function (Builder $query) use ($user): void {
            $query->where('creator_id', $user->id)
                ->orWhereHas('shares', fn (Builder $query): Builder => $this->recipientShares($query, $user));
        });
    }

    /** @return Builder<Board> */
    public function editableBy(User $user): Builder
    {
        return Board::query()->where(function (Builder $query) use ($user): void {
            $query->where('creator_id', $user->id)
                ->orWhereHas('shares', fn (Builder $query): Builder => $this->recipientShares($query, $user)
                    ->where('access', ShareAccess::Edit->value));
        });
    }

    /** @return Builder<Board> */
    public function ownedBy(User $user): Builder
    {
        return Board::query()->where('creator_id', $user->id);
    }

    /** @return Builder<Board> */
    public function sharedWith(User $user): Builder
    {
        return $this->viewableBy($user)->where('creator_id', '!=', $user->id);
    }

    public function findViewable(User $user, string $id): Board
    {
        return $this->viewableBy($user)->whereKey($id)->firstOrFail();
    }

    public function findEditable(User $user, string $id): Board
    {
        return $this->editableBy($user)->whereKey($id)->firstOrFail();
    }

    public function findOwned(User $user, string $id): Board
    {
        return $this->ownedBy($user)->whereKey($id)->firstOrFail();
    }

    public function findOwnedShare(User $user, string $id): BoardShare
    {
        return BoardShare::query()
            ->whereKey($id)
            ->whereHas('board', fn (Builder $query): Builder => $query->where('creator_id', $user->id))
            ->firstOrFail();
    }

    /**
     * @param  Builder<BoardShare>  $query
     * @return Builder<BoardShare>
     */
    private function recipientShares(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where('kind', BoardShareKind::Team->value)
                ->orWhere(function (Builder $query) use ($user): void {
                    $query->where('kind', BoardShareKind::User->value)
                        ->where('recipient_user_id', $user->id);
                });
        });
    }
}

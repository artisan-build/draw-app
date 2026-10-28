<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use App\Mcp\McpActorResolver;
use App\Models\Board;
use App\Services\BoardAccess;
use ArtisanBuild\BuiltForCloud\OperatorAbility;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;

trait AccessesBoards
{
    protected function actor(OperatorAbility $ability): User
    {
        return app(McpActorResolver::class)->resolve($ability);
    }

    protected function viewableBoard(User $actor, string $id): ?Board
    {
        return $this->board($id, app(BoardAccess::class)->viewableBy($actor));
    }

    protected function editableBoard(User $actor, string $id): ?Board
    {
        return $this->board($id, app(BoardAccess::class)->editableBy($actor));
    }

    protected function ownedBoard(User $actor, string $id): ?Board
    {
        return $this->board($id, app(BoardAccess::class)->ownedBy($actor));
    }

    protected function boardRefused(): Response
    {
        return Response::error('Board not found.');
    }

    /** @param Builder<Board> $query */
    private function board(string $id, $query): ?Board
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return $query->whereKey($id)->first();
    }
}

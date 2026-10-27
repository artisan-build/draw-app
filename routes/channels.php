<?php

declare(strict_types=1);

use App\Services\BoardAccess;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

Broadcast::channel('boards.{board}', function (User $user, string $board): bool {
    if (! Str::isUuid($board)) {
        return false;
    }

    return resolve(BoardAccess::class)->viewableBy($user)->whereKey($board)->exists();
});

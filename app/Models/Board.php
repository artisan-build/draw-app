<?php

declare(strict_types=1);

namespace App\Models;

use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property int $creator_id
 * @property string $title
 * @property array{elements: array<int, array<string, mixed>>, appState: array<string, mixed>, files: array<string, mixed>} $scene
 * @property int $revision
 *
 * @phpstan-type Scene array{elements: array<int, array<string, mixed>>, appState: array<string, mixed>, files: array<string, mixed>}
 *
 * @mixin IdeHelperBoard
 */
#[Fillable(['creator_id', 'title', 'scene'])]
class Board extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scene' => 'array',
            'revision' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Board $board): void {
            $board->id ??= (string) Str::uuid7();
            $board->scene ??= [
                'elements' => [],
                'appState' => [],
                'files' => [],
            ];
        });
    }
}

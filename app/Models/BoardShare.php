<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BoardShareKind;
use App\Enums\ShareAccess;
use ArtisanBuild\BuiltForCloud\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property string $id
 * @property string $board_id
 * @property BoardShareKind $kind
 * @property int|null $recipient_user_id
 * @property ShareAccess|null $access
 * @property CarbonInterface|null $expires_at
 * @property-read Board $board
 * @property-read User|null $recipient
 */
#[Fillable(['board_id', 'kind', 'recipient_user_id', 'access', 'expires_at'])]
class BoardShare extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @return BelongsTo<Board, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function signedUrl(string $route = 'board-shares.public.show'): string
    {
        if ($this->expires_at !== null) {
            return URL::temporarySignedRoute($route, $this->expires_at, ['share' => $this->id]);
        }

        return URL::signedRoute($route, ['share' => $this->id]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => BoardShareKind::class,
            'access' => ShareAccess::class,
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BoardShare $share): void {
            $share->id ??= (string) Str::uuid7();

            if ($share->kind === BoardShareKind::Link) {
                if ($share->recipient_user_id !== null || $share->access !== null) {
                    throw new LogicException('Signed links cannot carry a recipient or access level.');
                }

                return;
            }

            if ($share->expires_at !== null) {
                throw new LogicException('Only signed links may expire.');
            }

            if ($share->kind === BoardShareKind::User && $share->recipient_user_id === null) {
                throw new LogicException('Direct shares require a recipient.');
            }

            if ($share->kind === BoardShareKind::Team && $share->recipient_user_id !== null) {
                throw new LogicException('Team shares cannot carry a recipient.');
            }

            $share->access ??= ShareAccess::View;
        });

        static::updating(function (BoardShare $share): void {
            if ($share->isDirty(['kind', 'recipient_user_id', 'access', 'expires_at'])) {
                throw new LogicException('Share metadata is immutable; delete and recreate the share.');
            }
        });
    }
}

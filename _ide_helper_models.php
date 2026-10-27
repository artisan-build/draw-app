<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property string $id
 * @property int $creator_id
 * @property string $title
 * @property array{elements: array<int, array<string, mixed>>, appState: array<string, mixed>, files: array<string, mixed>} $scene
 * @property int $revision
 * @phpstan-type Scene array{elements: array<int, array<string, mixed>>, appState: array<string, mixed>, files: array<string, mixed>}
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \ArtisanBuild\BuiltForCloud\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BoardShare> $shares
 * @property-read int|null $shares_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board whereRevision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board whereScene($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBoard {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $board_id
 * @property BoardShareKind $kind
 * @property int|null $recipient_user_id
 * @property ShareAccess|null $access
 * @property CarbonInterface|null $expires_at
 * @property-read Board $board
 * @property-read User|null $recipient
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardShare newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardShare newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BoardShare query()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBoardShare {}
}

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
 * @property-read \ArtisanBuild\BuiltForCloud\User|null $creator
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Board query()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBoard {}
}

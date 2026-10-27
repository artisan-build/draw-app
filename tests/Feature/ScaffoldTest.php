<?php

declare(strict_types=1);

it('boots with the Draw application and deployment configuration', function (): void {
    $catalogManifest = json_decode(
        (string) file_get_contents(base_path('built-for-cloud.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $resourceKinds = collect($catalogManifest['resources'])->pluck('kind');

    expect(config('app.name'))->toBe('Draw')
        ->and(config('database.default'))->toBe('pgsql')
        ->and(config('built-for-cloud.manifest'))->toHaveKeys([
            'name',
            'slug',
            'description',
            'icon',
            'product_url',
        ])
        ->and($resourceKinds)->toContain('database')
        ->and($resourceKinds)->toContain('queue')
        ->and($resourceKinds)->not->toContain('websockets')
        ->and($catalogManifest['environment_keys'])->toBe([]);
});

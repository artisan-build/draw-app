<?php

declare(strict_types=1);

use App\Livewire\Boards\Index;
use App\Models\Board;
use ArtisanBuild\BuiltForCloud\User;
use ArtisanBuild\BuiltForCloud\UserRole;
use Illuminate\Support\Str;
use Livewire\Livewire;

function boardUser(UserRole $role, string $label): User
{
    $user = User::query()->create([
        'name' => $label,
        'email' => Str::slug($label).'-'.Str::lower(Str::random(8)).'@example.test',
    ]);

    $user->forceFill([
        'role' => $role->value,
        'email_verified_at' => now(),
    ])->save();

    return $user;
}

it('lets every admitted BfC role create a UUIDv7 board through the Livewire page', function (UserRole $role): void {
    $user = boardUser($role, "{$role->value} creator");

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('title', "{$role->value} board")
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $board = Board::query()->sole();

    expect($board->creator_id)->toBe($user->id)
        ->and($board->title)->toBe("{$role->value} board")
        ->and(Str::isUuid($board->id, 7))->toBeTrue()
        ->and($board->scene)->toBe([
            'elements' => [],
            'appState' => [],
            'files' => [],
        ])
        ->and($board->revision)->toBe(0);
})->with(UserRole::cases());

it('keeps private boards hidden from an unrelated role on every board surface', function (UserRole $unrelatedRole): void {
    $creator = boardUser(UserRole::Member, 'Board creator');
    $unrelated = boardUser($unrelatedRole, "Unrelated {$unrelatedRole->value}");
    $board = Board::query()->create([
        'creator_id' => $creator->id,
        'title' => 'Creator private board',
    ]);

    $this->actingAs($unrelated)
        ->get('/boards')
        ->assertOk()
        ->assertSee('data-testid="boards-list"', false)
        ->assertDontSee('Creator private board');

    $this->actingAs($unrelated)->get("/boards/{$board->id}")->assertNotFound();
    $this->actingAs($unrelated)->getJson("/boards/{$board->id}/scene")->assertNotFound();
    $this->actingAs($unrelated)->putJson("/boards/{$board->id}/scene", [
        'elements' => [],
    ])->assertNotFound();
})->with(UserRole::cases());

it('lets the creator list open fetch and mutate their board', function (): void {
    $creator = boardUser(UserRole::Member, 'Authorized creator');
    $board = Board::query()->create([
        'creator_id' => $creator->id,
        'title' => 'Test-created board',
    ]);

    $this->actingAs($creator)
        ->get('/boards')
        ->assertOk()
        ->assertSee('data-testid="boards-list"', false)
        ->assertSee('data-testid="board-list-item"', false)
        ->assertSee('Test-created board');

    $this->actingAs($creator)
        ->get("/boards/{$board->id}")
        ->assertOk()
        ->assertSee('data-testid="board-editor-shell"', false)
        ->assertSee('data-testid="editor-mount"', false)
        ->assertSee('Test-created board');

    $this->actingAs($creator)
        ->getJson("/boards/{$board->id}/scene")
        ->assertOk()
        ->assertJsonPath('scene.elements', [])
        ->assertJsonPath('revision', 0);

    $this->actingAs($creator)
        ->putJson("/boards/{$board->id}/scene", [
            'elements' => [[
                'id' => 'browser-element',
                'type' => 'rectangle',
                'version' => 1,
                'versionNonce' => 10,
            ]],
            'appState' => ['viewBackgroundColor' => '#ffffff'],
            'files' => [],
        ])
        ->assertOk()
        ->assertJsonPath('scene.elements.0.id', 'browser-element')
        ->assertJsonPath('revision', 1);
});

it('returns not found for malformed board UUIDs', function (): void {
    $user = boardUser(UserRole::Member, 'UUID tester');

    $this->actingAs($user)->get('/boards/not-a-uuid')->assertNotFound();
    $this->actingAs($user)->getJson('/boards/not-a-uuid/scene')->assertNotFound();
    $this->actingAs($user)->putJson('/boards/not-a-uuid/scene', [
        'elements' => [],
    ])->assertNotFound();
});

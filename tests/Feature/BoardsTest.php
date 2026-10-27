<?php

declare(strict_types=1);

use App\Livewire\Boards\Index;
use App\Models\Board;
use ArtisanBuild\BuiltForCloud\StandaloneAccess;
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

    return $user->refresh();
}

/** @return array<string, int> */
function boardSession(User $user): array
{
    $user->refresh();

    return [StandaloneAccess::SESSION_VERSION_KEY => $user->auth_session_version];
}

it('lets every admitted BfC role create a UUIDv7 board through the Livewire page', function (UserRole $role): void {
    $user = boardUser($role, "{$role->value} creator");

    $this->actingAs($user)->withSession(boardSession($user));

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

    $this->actingAs($unrelated)->withSession(boardSession($unrelated));

    $this->get('/boards')
        ->assertOk()
        ->assertSee('data-testid="boards-list"', false)
        ->assertDontSee('Creator private board');

    $this->get("/boards/{$board->id}")->assertNotFound();
    $this->getJson("/boards/{$board->id}/scene")->assertNotFound();
    $this->putJson("/boards/{$board->id}/scene", [])->assertNotFound();
})->with(UserRole::cases());

it('lets the creator list open fetch and mutate their board', function (): void {
    $creator = boardUser(UserRole::Member, 'Authorized creator');
    $board = Board::query()->create([
        'creator_id' => $creator->id,
        'title' => 'Test-created board',
    ]);

    $this->actingAs($creator)->withSession(boardSession($creator));

    $this->get('/boards')
        ->assertOk()
        ->assertSee('data-testid="boards-list"', false)
        ->assertSee('data-testid="board-list-item"', false)
        ->assertSee('Test-created board');

    $this->get("/boards/{$board->id}")
        ->assertOk()
        ->assertSee('data-testid="board-editor-shell"', false)
        ->assertSee('data-testid="editor-mount"', false)
        ->assertSee('Test-created board');

    $this->getJson("/boards/{$board->id}/scene")
        ->assertOk()
        ->assertJsonPath('scene.elements', [])
        ->assertJsonPath('revision', 0);

    $this->putJson("/boards/{$board->id}/scene", [
        'elements' => [],
    ])
        ->assertOk()
        ->assertJsonPath('scene.elements', [])
        ->assertJsonPath('revision', 1);

    $this->putJson("/boards/{$board->id}/scene", [
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
        ->assertJsonPath('revision', 2);
});

it('returns not found for malformed board UUIDs', function (): void {
    $user = boardUser(UserRole::Member, 'UUID tester');

    $this->actingAs($user)->withSession(boardSession($user));

    $this->get('/boards/not-a-uuid')->assertNotFound();
    $this->getJson('/boards/not-a-uuid/scene')->assertNotFound();
    $this->putJson('/boards/not-a-uuid/scene', [
        'elements' => [],
    ])->assertNotFound();
});

it('redirects a guest board request to the BfC login', function (): void {
    $this->get('/boards')->assertRedirect(route('bfc.login', [
        'intended' => '/boards',
    ]));
});

it('refuses a deactivated user on board list read and write surfaces', function (): void {
    $user = boardUser(UserRole::Member, 'Deactivated user');
    $board = Board::query()->create([
        'creator_id' => $user->id,
        'title' => 'Unavailable board',
    ]);
    $user->forceFill([
        'status' => 'deactivated',
        'deactivated_at' => now(),
    ])->save();

    $this->actingAs($user)->withSession(boardSession($user));
    $this->get('/boards')->assertForbidden();

    $this->actingAs($user)->withSession(boardSession($user));
    $this->getJson("/boards/{$board->id}/scene")->assertForbidden();

    $this->actingAs($user)->withSession(boardSession($user));
    $this->putJson("/boards/{$board->id}/scene", ['elements' => []])->assertForbidden();
});

it('ends a stale BfC session after the user session version changes', function (): void {
    $user = boardUser(UserRole::Member, 'Stale session user');
    $oldVersion = $user->auth_session_version;
    $user->forceFill(['auth_session_version' => $oldVersion + 1])->save();

    $this->actingAs($user)->withSession([
        StandaloneAccess::SESSION_VERSION_KEY => $oldVersion,
    ]);

    $this->get('/boards')->assertRedirect(route('bfc.login', [
        'intended' => '/boards',
    ]));
    $this->assertGuest();
});

it('admits an active BfC user whose email is not locally verified', function (): void {
    $user = boardUser(UserRole::Member, 'Managed unverified user');
    $user->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($user)->withSession(boardSession($user));

    $this->get('/boards')
        ->assertOk()
        ->assertSee('data-testid="board-create-form"', false);

    Livewire::test(Index::class)
        ->set('title', 'Managed user board')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Board::query()->sole()->creator_id)->toBe($user->id);
});

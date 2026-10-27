<?php

declare(strict_types=1);

use App\Enums\BoardShareKind;
use App\Enums\ShareAccess;
use App\Events\BoardUpdated;
use App\Models\Board;
use App\Services\BoardSceneService;
use ArtisanBuild\BuiltForCloud\StandaloneAccess;
use ArtisanBuild\BuiltForCloud\User;
use ArtisanBuild\BuiltForCloud\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function realtimeUser(string $label, UserRole $role = UserRole::Member): User
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
function realtimeSession(User $user): array
{
    return [StandaloneAccess::SESSION_VERSION_KEY => $user->refresh()->auth_session_version];
}

function realtimeBoard(User $creator, array $scene = []): Board
{
    return Board::query()->create([
        'creator_id' => $creator->id,
        'title' => 'Realtime test board',
        'scene' => $scene === [] ? null : $scene,
    ]);
}

function useRealtimeBroadcaster(): void
{
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
}

it('round-trips complete editor snapshots and keeps view recipients read only', function (): void {
    $creator = realtimeUser('Round trip creator');
    $viewer = realtimeUser('Round trip viewer');
    $editor = realtimeUser('Round trip editor');
    $board = realtimeBoard($creator, [
        'elements' => [['id' => 'seed', 'type' => 'rectangle', 'version' => 1]],
        'appState' => ['viewBackgroundColor' => '#eeeeee'],
        'files' => [],
    ]);
    $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $viewer->id,
        'access' => ShareAccess::View,
    ]);
    $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $editor->id,
        'access' => ShareAccess::Edit,
    ]);

    $this->actingAs($creator)->withSession(realtimeSession($creator));
    $this->get("/boards/{$board->id}")
        ->assertOk()
        ->assertSee('data-testid="editor-mount"', false)
        ->assertSee('data-board-canvas', false)
        ->assertSee('data-can-edit="true"', false)
        ->assertSee('data-channel-kind="private"', false);

    $this->putJson("/boards/{$board->id}/scene", [
        'revision' => 0,
        'elements' => [['id' => 'hand-drawn', 'type' => 'freedraw', 'version' => 1]],
        'appState' => [
            'viewBackgroundColor' => '#ffffff',
            'gridSize' => 20,
            'gridStep' => 5,
            'gridModeEnabled' => true,
            'collaborators' => ['must-not-persist'],
        ],
        'files' => ['file-1' => ['id' => 'file-1', 'mimeType' => 'image/png']],
    ])->assertOk()->assertJsonPath('revision', 1);

    $this->getJson("/boards/{$board->id}/scene")
        ->assertOk()
        ->assertJsonPath('scene.elements.0.id', 'hand-drawn')
        ->assertJsonMissingPath('scene.appState.collaborators')
        ->assertJsonPath('scene.files.file-1.id', 'file-1');

    $this->putJson("/boards/{$board->id}/scene", [
        'revision' => 1,
        'elements' => [],
        'appState' => [],
        'files' => [],
    ])->assertOk()
        ->assertJsonPath('revision', 2)
        ->assertJsonPath('scene.elements', [])
        ->assertJsonPath('scene.appState', [])
        ->assertJsonPath('scene.files', []);

    $this->actingAs($viewer)->withSession(realtimeSession($viewer));
    $this->get("/boards/{$board->id}")
        ->assertOk()
        ->assertSee('data-can-edit="false"', false);
    $this->putJson("/boards/{$board->id}/scene", [
        'revision' => 2,
        'elements' => [],
    ])->assertNotFound();

    $this->actingAs($editor)->withSession(realtimeSession($editor));
    $this->get("/boards/{$board->id}")
        ->assertOk()
        ->assertSee('data-can-edit="true"', false);
});

it('rejects stale editor snapshots without overwriting a newer clear', function (): void {
    $creator = realtimeUser('Stale save creator');
    $board = realtimeBoard($creator, [
        'elements' => [['id' => 'old', 'type' => 'rectangle', 'version' => 1]],
        'appState' => [],
        'files' => [],
    ]);
    resolve(BoardSceneService::class)->clear($creator, $board);

    $this->actingAs($creator)->withSession(realtimeSession($creator));
    $this->putJson("/boards/{$board->id}/scene", [
        'revision' => 0,
        'elements' => [['id' => 'stale', 'type' => 'rectangle', 'version' => 2]],
    ])->assertConflict();

    expect($board->fresh()->revision)->toBe(1)
        ->and($board->fresh()->scene['elements'])->toBe([]);
});

it('broadcasts an immediate revision-only signal on private and live opaque link channels', function (): void {
    Event::fake([BoardUpdated::class]);
    $creator = realtimeUser('Broadcast creator');
    $board = realtimeBoard($creator);
    $live = $board->shares()->create(['kind' => BoardShareKind::Link]);
    $expired = $board->shares()->create([
        'kind' => BoardShareKind::Link,
        'expires_at' => now()->subMinute(),
    ]);

    resolve(BoardSceneService::class)->clear($creator, $board);

    Event::assertDispatched(BoardUpdated::class, function (BoardUpdated $event) use ($board, $live, $expired): bool {
        $channels = array_map(strval(...), $event->broadcastOn());

        expect($event->boardId)->toBe($board->id)
            ->and($event->revision)->toBe(1)
            ->and($event->broadcastWith())->toBe(['revision' => 1])
            ->and($channels)->toContain('private-boards.'.$board->id)
            ->and($channels)->toContain('link-shares.'.$live->id)
            ->and($channels)->not->toContain('link-shares.'.$expired->id);

        return true;
    });
});

it('does not broadcast refused or rolled-back scene mutations', function (): void {
    Event::fake([BoardUpdated::class]);
    $creator = realtimeUser('Rollback creator');
    $viewer = realtimeUser('Rollback viewer');
    $board = realtimeBoard($creator);
    $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $viewer->id,
        'access' => ShareAccess::View,
    ]);

    expect(fn (): Board => resolve(BoardSceneService::class)->clear($viewer, $board))
        ->toThrow(NotFoundHttpException::class);
    Event::assertNotDispatched(BoardUpdated::class);

    expect(function () use ($creator, $board): void {
        DB::transaction(function () use ($creator, $board): void {
            resolve(BoardSceneService::class)->clear($creator, $board);
            throw new RuntimeException('force outer rollback');
        });
    })->toThrow(RuntimeException::class);

    Event::assertNotDispatched(BoardUpdated::class);
    expect($board->fresh()->revision)->toBe(0);
});

it('keeps a committed scene successful when immediate broadcasting fails', function (): void {
    $creator = realtimeUser('Broadcast failure creator');
    $board = realtimeBoard($creator);
    Event::listen(BoardUpdated::class, fn (): never => throw new RuntimeException('reverb unavailable'));

    $saved = resolve(BoardSceneService::class)->clear($creator, $board);

    expect($saved->revision)->toBe(1)
        ->and($board->fresh()->revision)->toBe(1);
});

it('authorizes private board channels only for current viewable BfC users', function (BoardShareKind $kind, ShareAccess $access): void {
    useRealtimeBroadcaster();
    $creator = realtimeUser("Channel {$kind->value} creator");
    $recipient = realtimeUser("Channel {$kind->value} {$access->value} recipient");
    $board = realtimeBoard($creator);
    $board->shares()->create([
        'kind' => $kind,
        'recipient_user_id' => $kind === BoardShareKind::User ? $recipient->id : null,
        'access' => $access,
    ]);

    foreach ([$creator, $recipient] as $actor) {
        $this->actingAs($actor)->withSession(realtimeSession($actor));
        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-boards.'.$board->id,
            'socket_id' => '1234.5678',
        ])->assertOk();
    }
})->with([
    'direct view' => [BoardShareKind::User, ShareAccess::View],
    'direct edit' => [BoardShareKind::User, ShareAccess::Edit],
    'team view' => [BoardShareKind::Team, ShareAccess::View],
    'team edit' => [BoardShareKind::Team, ShareAccess::Edit],
]);

it('refuses unrelated roles inactive users stale sessions and malformed private channels', function (UserRole $role): void {
    useRealtimeBroadcaster();
    $creator = realtimeUser('Contained channel creator');
    $unrelated = realtimeUser('Contained unrelated '.$role->value, $role);
    $board = realtimeBoard($creator);
    $payload = [
        'channel_name' => 'private-boards.'.$board->id,
        'socket_id' => '1234.5678',
    ];

    $this->actingAs($unrelated)->withSession(realtimeSession($unrelated));
    $this->postJson('/broadcasting/auth', $payload)->assertForbidden();

    $unrelated->forceFill(['status' => 'deactivated', 'deactivated_at' => now()])->save();
    $this->actingAs($unrelated)->withSession(realtimeSession($unrelated));
    $this->postJson('/broadcasting/auth', $payload)->assertForbidden();

    $staleVersion = $creator->auth_session_version;
    $creator->forceFill(['auth_session_version' => $staleVersion + 1])->save();
    $this->actingAs($creator)->withSession([StandaloneAccess::SESSION_VERSION_KEY => $staleVersion]);
    $this->postJson('/broadcasting/auth', $payload)->assertUnauthorized();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $creator->refresh();
    $this->actingAs($creator)->withSession(realtimeSession($creator));
    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-boards.not-a-uuid',
        'socket_id' => '1234.5678',
    ])->assertForbidden();

    expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, '"boards"')))->toBeFalse();
})->with(UserRole::cases());

it('lets a valid signed viewer refetch a signaled revision and fails closed after revocation', function (): void {
    Event::fake([BoardUpdated::class]);
    $creator = realtimeUser('Link convergence creator');
    $board = realtimeBoard($creator);
    $share = $board->shares()->create(['kind' => BoardShareKind::Link]);
    $sceneUrl = $share->signedUrl('board-shares.public.scene');

    resolve(BoardSceneService::class)->addElements($creator, $board, [[
        'id' => 'live-element',
        'type' => 'rectangle',
        'version' => 1,
    ]]);

    $this->getJson($sceneUrl)
        ->assertOk()
        ->assertJsonPath('revision', 1)
        ->assertJsonPath('scene.elements.0.id', 'live-element');

    $share->delete();
    $this->getJson($sceneUrl)->assertNotFound();
});

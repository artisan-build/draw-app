<?php

declare(strict_types=1);

use App\Enums\BoardShareKind;
use App\Enums\ShareAccess;
use App\Models\Board;
use App\Models\BoardShare;
use App\Services\BoardAccess;
use App\Services\BoardSceneService;
use App\Services\BoardShareService;
use ArtisanBuild\BuiltForCloud\StandaloneAccess;
use ArtisanBuild\BuiltForCloud\User;
use ArtisanBuild\BuiltForCloud\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function sharingUser(string $label, UserRole $role = UserRole::Member): User
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
function sharingSession(User $user): array
{
    return [StandaloneAccess::SESSION_VERSION_KEY => $user->refresh()->auth_session_version];
}

function sharingBoard(User $creator, string $title = 'Shared board'): Board
{
    return Board::query()->create([
        'creator_id' => $creator->id,
        'title' => $title,
    ]);
}

it('stores UUIDv7 shares with enum access defaults and database-enforced link shape', function (): void {
    $creator = sharingUser('Share schema creator');
    $recipient = sharingUser('Share schema recipient');
    $board = sharingBoard($creator);

    $view = $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $recipient->id,
    ]);
    $edit = sharingBoard($creator, 'Team schema board')->shares()->create([
        'kind' => BoardShareKind::Team,
        'access' => ShareAccess::Edit,
    ]);
    $link = $board->shares()->create(['kind' => BoardShareKind::Link]);

    expect(Str::isUuid($view->id, 7))->toBeTrue()
        ->and($view->access)->toBe(ShareAccess::View)
        ->and($edit->access)->toBe(ShareAccess::Edit)
        ->and($link->access)->toBeNull();

    expect(fn (): BoardShare => $board->shares()->create([
        'kind' => BoardShareKind::Link,
        'access' => ShareAccess::Edit,
    ]))->toThrow(LogicException::class);
    expect(fn (): BoardShare => resolve(BoardShareService::class)->create(
        $creator,
        $board,
        BoardShareKind::Link,
        access: ShareAccess::Edit,
    ))->toThrow(LogicException::class);

    $this->actingAs($creator)->withSession(sharingSession($creator));
    $this->post("/boards/{$board->id}/shares", [
        'kind' => 'link',
        'access' => 'edit',
    ])->assertSessionHasErrors('access');

    expect(fn (): bool => DB::table('board_shares')->insert([
        'id' => (string) Str::uuid7(),
        'board_id' => $board->id,
        'kind' => BoardShareKind::Link->value,
        'access' => ShareAccess::Edit->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('lists direct and team shares only under Shared with me and grants board reads', function (): void {
    $creator = sharingUser('Read share creator');
    $recipient = sharingUser('Direct recipient');
    $teamRecipient = sharingUser('Team recipient');
    $directBoard = sharingBoard($creator, 'Direct test board');
    $teamBoard = sharingBoard($creator, 'Team test board');
    $directBoard->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $recipient->id,
    ]);
    $teamBoard->shares()->create(['kind' => BoardShareKind::Team]);

    $this->actingAs($recipient)->withSession(sharingSession($recipient));
    $this->get('/boards')
        ->assertOk()
        ->assertSee('data-testid="boards-list"', false)
        ->assertDontSee('Direct test board')
        ->assertDontSee('Team test board');
    $this->get('/shared-with-me')
        ->assertOk()
        ->assertSee('data-testid="shared-boards-list"', false)
        ->assertSee('Direct test board')
        ->assertSee('Team test board');
    $this->get("/boards/{$directBoard->id}")->assertOk();
    $this->getJson("/boards/{$directBoard->id}/scene")->assertOk()->assertJsonPath('revision', 0);
    $this->putJson("/boards/{$directBoard->id}/scene", ['elements' => []])->assertNotFound();

    $editableBoard = sharingBoard($creator, 'Editable direct board');
    $editableBoard->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $recipient->id,
        'access' => ShareAccess::Edit,
    ]);
    $this->putJson("/boards/{$editableBoard->id}/scene", ['revision' => 0, 'elements' => []])
        ->assertOk()
        ->assertJsonPath('revision', 1);

    $this->actingAs($teamRecipient)->withSession(sharingSession($teamRecipient));
    $this->get('/shared-with-me')->assertOk()->assertSee('Team test board');
    $this->get("/boards/{$teamBoard->id}")->assertOk();
    $this->getJson("/boards/{$teamBoard->id}/scene")->assertOk();
});

it('keeps unrelated owner admin and member peers outside private and shared boards', function (UserRole $role): void {
    $creator = sharingUser('Peer isolation creator');
    $recipient = sharingUser('Peer isolation recipient');
    $unrelated = sharingUser("Unrelated {$role->value}", $role);
    $board = sharingBoard($creator, 'Peer-isolated board');
    $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $recipient->id,
        'access' => ShareAccess::Edit,
    ]);

    $this->actingAs($unrelated)->withSession(sharingSession($unrelated));
    $this->get('/shared-with-me')->assertOk()->assertDontSee('Peer-isolated board');
    $this->get("/boards/{$board->id}")->assertNotFound();
    $this->getJson("/boards/{$board->id}/scene")->assertNotFound();
    $this->putJson("/boards/{$board->id}/scene", ['elements' => []])->assertNotFound();
})->with(UserRole::cases());

it('rechecks every scene mutation family under the board lock for view and edit shares', function (): void {
    $creator = sharingUser('Mutation creator');
    $viewer = sharingUser('Mutation viewer');
    $editor = sharingUser('Mutation editor');
    $scenes = resolve(BoardSceneService::class);
    $operations = [
        'editor save' => fn (User $actor, Board $board): Board => $scenes->saveEditorScene($actor, $board, ['elements' => []], 0),
        'add elements' => fn (User $actor, Board $board): Board => $scenes->addElements($actor, $board, [['id' => 'added', 'type' => 'rectangle', 'version' => 1]]),
        'update elements' => fn (User $actor, Board $board): Board => $scenes->updateElements($actor, $board, [['id' => 'seed', 'x' => 25]]),
        'delete elements' => fn (User $actor, Board $board): Board => $scenes->deleteElements($actor, $board, ['seed']),
        'clear' => $scenes->clear(...),
        'skeleton' => fn (User $actor, Board $board): Board => $scenes->addSkeletons($actor, $board, [['type' => 'rectangle', 'x' => 0, 'y' => 0, 'w' => 20, 'h' => 20]]),
        'mermaid' => fn (User $actor, Board $board): Board => $scenes->addMermaid($actor, $board, 'flowchart LR; A --> B'),
        'slide' => fn (User $actor, Board $board): Board => $scenes->addSlide($actor, $board, 'Test slide', []),
    ];

    foreach ($operations as $label => $operation) {
        $board = Board::query()->create([
            'creator_id' => $creator->id,
            'title' => $label,
            'scene' => [
                'elements' => [['id' => 'seed', 'type' => 'rectangle', 'version' => 1, 'versionNonce' => 1]],
                'appState' => [],
                'files' => [],
            ],
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

        expect(fn (): Board => $operation($viewer, $board))->toThrow(NotFoundHttpException::class)
            ->and($board->fresh()->revision)->toBe(0);
        expect($operation($editor, $board)->revision)->toBe(1);
    }
});

it('revokes pre-authorized direct and team writes by serializing deletion on the same board lock', function (BoardShareKind $kind): void {
    $creator = sharingUser("Revocation {$kind->value} creator");
    $recipient = sharingUser("Revocation {$kind->value} recipient");
    $board = sharingBoard($creator, "Revocation {$kind->value} board");
    $share = $board->shares()->create([
        'kind' => $kind,
        'recipient_user_id' => $kind === BoardShareKind::User ? $recipient->id : null,
        'access' => ShareAccess::Edit,
    ]);
    $preAuthorizedBoard = resolve(BoardAccess::class)->findEditable($recipient, $board->id);
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains(strtolower($query->sql), 'for update')) {
            $queries[] = [$query->sql, $query->bindings];
        }
    });

    resolve(BoardShareService::class)->delete($creator, $share);

    expect(fn (): Board => resolve(BoardSceneService::class)->clear($recipient, $preAuthorizedBoard))
        ->toThrow(NotFoundHttpException::class);
    expect($queries)->toHaveCount(2)
        ->and(collect($queries)->every(fn (array $query): bool => str_contains($query[0], '"boards"') && in_array($board->id, $query[1], true)))->toBeTrue();

    $this->actingAs($recipient)->withSession(sharingSession($recipient));
    $this->get('/shared-with-me')->assertOk()->assertDontSee("Revocation {$kind->value} board");
    $this->get("/boards/{$board->id}")->assertNotFound();
    $this->getJson("/boards/{$board->id}/scene")->assertNotFound();
    $this->putJson("/boards/{$board->id}/scene", ['elements' => []])->assertNotFound();
})->with([BoardShareKind::User, BoardShareKind::Team]);

it('allows only creators to create and delete shares and exposes no share update endpoint', function (UserRole $role): void {
    $creator = sharingUser('Share manager creator', $role);
    $editor = sharingUser('Share manager editor');
    $target = sharingUser('Share manager target');
    $unrelated = sharingUser("Unrelated share manager {$role->value}");
    $board = sharingBoard($creator, 'Creator-managed board');
    $editShare = $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $editor->id,
        'access' => ShareAccess::Edit,
    ]);

    foreach ([$editor, $unrelated] as $actor) {
        $this->actingAs($actor)->withSession(sharingSession($actor));
        $this->post("/boards/{$board->id}/shares", [
            'kind' => 'user',
            'recipient_user_id' => $target->id,
            'access' => 'edit',
        ])->assertNotFound();
        $this->delete("/shares/{$editShare->id}")->assertNotFound();
    }

    $this->actingAs($creator)->withSession(sharingSession($creator));
    $this->post("/boards/{$board->id}/shares", ['kind' => 'team', 'access' => 'view'])
        ->assertRedirect('/shares');
    $teamShare = $board->shares()->where('kind', BoardShareKind::Team->value)->sole();
    $this->put("/shares/{$teamShare->id}", ['access' => 'edit'])->assertMethodNotAllowed();
    $this->patch("/shares/{$teamShare->id}", ['access' => 'edit'])->assertMethodNotAllowed();
    $this->delete("/shares/{$teamShare->id}")->assertRedirect();
})->with(UserRole::cases());

it('shows every creator-owned share with its immutable access label', function (): void {
    $creator = sharingUser('Share list creator');
    $recipient = sharingUser('Share list recipient');
    $board = sharingBoard($creator, 'Access-labelled board');
    $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $recipient->id,
        'access' => ShareAccess::Edit,
    ]);
    $board->shares()->create([
        'kind' => BoardShareKind::Team,
        'access' => ShareAccess::View,
    ]);
    $board->shares()->create(['kind' => BoardShareKind::Link]);

    $this->actingAs($creator)->withSession(sharingSession($creator));
    $response = $this->get('/shares')
        ->assertOk()
        ->assertSee('data-testid="my-shares-list"', false)
        ->assertSee('Access-labelled board')
        ->assertSee($recipient->email);

    preg_match_all(
        '/data-testid="share-access-label">\s*(Edit|View|View only)\s*<\/span>/',
        $response->getContent(),
        $labels,
    );
    sort($labels[1]);

    expect(substr_count($response->getContent(), 'data-testid="my-share-list-item"'))->toBe(3)
        ->and($labels[1])->toBe(['Edit', 'View', 'View only']);
});

it('serves anonymous revocable signed links with expiry frame compatibility and no chrome or writes', function (): void {
    $this->freezeTime(function (): void {
        $creator = sharingUser('Signed link creator');
        $board = sharingBoard($creator, 'Anonymous viewer board');
        $share = $board->shares()->create([
            'kind' => BoardShareKind::Link,
            'expires_at' => now()->addMinute(),
        ]);
        $url = $share->signedUrl();

        $this->get($url)
            ->assertOk()
            ->assertSee('data-testid="public-viewer-mount"', false)
            ->assertSee('data-can-edit="false"', false)
            ->assertSee('data-channel-kind="public"', false)
            ->assertSee('data-channel-name="link-shares.'.$share->id.'"', false)
            ->assertDontSee($board->id, false)
            ->assertDontSee('<header', false)
            ->assertDontSee('Shared with me');
        $this->get($url.'&frame=slide-1')->assertOk();
        $this->get($url.'&unexpected=1')->assertNotFound();
        $this->get($share->signedUrl('board-shares.public.scene'))
            ->assertOk()
            ->assertJsonPath('scene.elements', [])
            ->assertJsonPath('revision', 0);
        $this->putJson("/s/{$share->id}", ['elements' => []])->assertMethodNotAllowed();

        $this->travel(2)->minutes();
        $this->get($url)->assertNotFound();
    });
});

it('returns plain not found for revoked wrong-kind malformed and tampered public links', function (): void {
    $creator = sharingUser('Invalid link creator');
    $recipient = sharingUser('Invalid link recipient');
    $board = sharingBoard($creator);
    $link = $board->shares()->create(['kind' => BoardShareKind::Link]);
    $linkUrl = $link->signedUrl();
    $userShare = $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $recipient->id,
    ]);

    $this->get('/s/not-a-uuid')->assertNotFound();
    $this->get('/s/not-a-uuid/scene')->assertNotFound();
    $this->get($userShare->signedUrl())->assertNotFound();
    $this->get($linkUrl.'&signature=changed')->assertNotFound();

    $link->delete();
    $this->get($linkUrl)->assertNotFound();
    $this->get($link->signedUrl('board-shares.public.scene'))->assertNotFound();
});

it('keeps BfC admission on authenticated sharing pages and management actions', function (): void {
    $creator = sharingUser('Admission creator');
    $recipient = sharingUser('Admission recipient');
    $board = sharingBoard($creator);
    $share = $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $recipient->id,
    ]);

    $this->get('/shared-with-me')->assertRedirect(route('bfc.login', ['intended' => '/shared-with-me']));
    $this->get('/shares')->assertRedirect(route('bfc.login', ['intended' => '/shares']));

    $creator->forceFill(['status' => 'deactivated', 'deactivated_at' => now()])->save();
    $this->actingAs($creator)->withSession(sharingSession($creator));
    $this->get('/shares')->assertForbidden();
    $this->actingAs($creator)->withSession(sharingSession($creator));
    $this->post("/boards/{$board->id}/shares", ['kind' => 'team'])->assertForbidden();
    $this->actingAs($creator)->withSession(sharingSession($creator));
    $this->delete("/shares/{$share->id}")->assertForbidden();

    $oldVersion = $recipient->auth_session_version;
    $recipient->forceFill(['auth_session_version' => $oldVersion + 1])->save();
    $this->actingAs($recipient)->withSession([StandaloneAccess::SESSION_VERSION_KEY => $oldVersion]);
    $this->get('/shared-with-me')->assertRedirect(route('bfc.login', ['intended' => '/shared-with-me']));
    $this->assertGuest();
});

it('stops malformed share and board UUIDs before PostgreSQL binding', function (): void {
    $creator = sharingUser('Sharing UUID creator');
    $this->actingAs($creator)->withSession(sharingSession($creator));

    $this->post('/boards/not-a-uuid/shares', ['kind' => 'team'])->assertNotFound();
    $this->delete('/shares/not-a-uuid')->assertNotFound();
});

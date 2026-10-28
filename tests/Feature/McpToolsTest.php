<?php

declare(strict_types=1);

use App\Enums\BoardShareKind;
use App\Enums\ShareAccess;
use App\Events\BoardUpdated;
use App\Mcp\Servers\DrawServer;
use App\Mcp\Tools\AddElementsTool;
use App\Mcp\Tools\AddMermaidTool;
use App\Mcp\Tools\AddSlideTool;
use App\Mcp\Tools\ClearBoardTool;
use App\Mcp\Tools\CreateBoardTool;
use App\Mcp\Tools\DeleteElementsTool;
use App\Mcp\Tools\GetBoardTool;
use App\Mcp\Tools\ListBoardsTool;
use App\Mcp\Tools\ShareBoardLinkTool;
use App\Mcp\Tools\UpdateElementsTool;
use App\Models\Board;
use ArtisanBuild\BuiltForCloud\AuthorityMode;
use ArtisanBuild\BuiltForCloud\Console\ActingPrincipal;
use ArtisanBuild\BuiltForCloud\Console\ConsoleRole;
use ArtisanBuild\BuiltForCloud\Credential;
use ArtisanBuild\BuiltForCloud\CredentialKind;
use ArtisanBuild\BuiltForCloud\CredentialPurpose;
use ArtisanBuild\BuiltForCloud\InstallationAuthority;
use ArtisanBuild\BuiltForCloud\OffboardedSubject;
use ArtisanBuild\BuiltForCloud\OperatorAbility;
use ArtisanBuild\BuiltForCloud\SubjectType;
use ArtisanBuild\BuiltForCloud\Testing\McpDelegatedEnforcement;
use ArtisanBuild\BuiltForCloud\User;
use ArtisanBuild\BuiltForCloud\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/** @return list<class-string<Tool>> */
function drawMcpTools(): array
{
    return [
        ListBoardsTool::class,
        CreateBoardTool::class,
        GetBoardTool::class,
        AddElementsTool::class,
        UpdateElementsTool::class,
        DeleteElementsTool::class,
        ClearBoardTool::class,
        AddMermaidTool::class,
        AddSlideTool::class,
        ShareBoardLinkTool::class,
    ];
}

function mcpUser(string $label, UserRole $role = UserRole::Member, ?string $scalpelsId = null): User
{
    $user = User::query()->create([
        'name' => $label,
        'email' => Str::slug($label).'-'.Str::lower(Str::random(8)).'@example.test',
    ]);
    $user->forceFill([
        'role' => $role->value,
        'status' => 'active',
        'scalpels_issuer' => $scalpelsId === null ? null : 'https://bfc-enforcement.test',
        'scalpels_connection_id' => $scalpelsId === null ? null : 'draw-test-connection',
        'scalpels_id' => $scalpelsId,
        'membership_confirmed_at' => $scalpelsId === null ? null : now(),
        'managed_membership_status' => $scalpelsId === null ? null : 'active',
    ])->save();

    return $user->refresh();
}

function enableDelegatedMcpProjection(): void
{
    config(['built-for-cloud.managed.client_secret' => 'test-only-managed-secret']);
    DB::table('bfc_authority')
        ->where('key', InstallationAuthority::KEY)
        ->update([
            'mode' => AuthorityMode::Managed->value,
            'generation' => 2,
            'issuer' => 'https://bfc-enforcement.test',
            'connection_id' => 'draw-test-connection',
            'organization_id' => 'draw-test-organization',
            'installation_id' => 'draw-test-installation',
            'authority_base_url' => 'https://scalpels.example.test',
            'managed_connection_status' => 'active',
            'updated_at' => now(),
        ]);
}

/** @param list<string>|null $abilities */
function mcpCredential(User $user, ?array $abilities): string
{
    $plaintext = 'draw-mcp-'.Str::random(48);
    Credential::query()->create([
        'kind' => CredentialKind::Bearer,
        'purpose' => CredentialPurpose::Mcp,
        'subject_type' => SubjectType::UserPrincipal,
        'subject_ref' => 'draw-user:'.$user->id,
        'name' => 'Draw MCP test',
        'user_id' => (string) $user->id,
        'abilities' => $abilities,
        'secret_hash' => hash('sha256', $plaintext),
    ]);

    return $plaintext;
}

function prefixSimilarMcpCredential(User $user, string $ability): string
{
    $plaintext = 'draw-mcp-'.Str::random(48);
    $credential = Credential::query()->create([
        'kind' => CredentialKind::Bearer,
        'purpose' => CredentialPurpose::Mcp,
        'subject_type' => SubjectType::UserPrincipal,
        'subject_ref' => 'draw-user:'.$user->id,
        'name' => 'Draw MCP prefix test',
        'user_id' => (string) $user->id,
        'abilities' => null,
        'secret_hash' => hash('sha256', $plaintext),
    ]);
    DB::table($credential->getTable())->where('id', $credential->id)->update([
        'abilities' => json_encode([$ability], JSON_THROW_ON_ERROR),
    ]);

    return $plaintext;
}

/** @param array<string, mixed> $arguments */
function callMcp(string $path, string $tool, array $arguments, string $credential, int $id = 1): TestResponse
{
    return test()->postJson($path, [
        'jsonrpc' => '2.0',
        'id' => $id,
        'method' => 'tools/call',
        'params' => ['name' => $tool, 'arguments' => $arguments],
    ], [
        'Authorization' => 'Bearer '.$credential,
        'Accept' => 'application/json, text/event-stream',
    ]);
}

/** @return array<class-string<Tool>, array<string, mixed>> */
function delegatedMcpArguments(string $boardId): array
{
    return [
        CreateBoardTool::class => ['title' => 'Delegated created board'],
        GetBoardTool::class => ['id' => $boardId],
        AddElementsTool::class => ['id' => $boardId, 'elements' => [['id' => 'added', 'type' => 'ellipse', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 80]]],
        UpdateElementsTool::class => ['id' => $boardId, 'elements' => [['id' => 'seed', 'x' => 40]]],
        DeleteElementsTool::class => ['id' => $boardId, 'element_ids' => ['seed']],
        ClearBoardTool::class => ['id' => $boardId],
        AddMermaidTool::class => ['id' => $boardId, 'mermaid' => 'flowchart LR; A[One] --> B[Two]'],
        AddSlideTool::class => ['id' => $boardId, 'title' => 'Delegated slide', 'elements' => []],
        ShareBoardLinkTool::class => ['id' => $boardId, 'expires_in_minutes' => 30],
    ];
}

it('declares the real BfC doors and conforms every tool under a verified delegated assertion', function (): void {
    $this->assertBuiltForCloudMcpProductAdmission();
    enableDelegatedMcpProjection();
    $primary = mcpUser('Delegated primary', scalpelsId: 'bfc-enforcement-primary');
    mcpUser('Delegated secondary', scalpelsId: 'bfc-enforcement-secondary');
    $board = Board::query()->create([
        'creator_id' => $primary->id,
        'title' => 'Delegated board',
        'scene' => [
            'elements' => [['id' => 'seed', 'type' => 'rectangle', 'version' => 1, 'versionNonce' => 1]],
            'appState' => [],
            'files' => [],
        ],
    ]);

    $this->assertBuiltForCloudMcpDelegatedTools(DrawServer::class);
    $this->getJson('/bfc/meta')
        ->assertOk()
        ->assertJsonPath('endpoints.mcp', '/mcp')
        ->assertJsonPath('endpoints.mcp_write', '/mcp/write')
        ->assertJsonPath('endpoints.mcp_destructive', '/mcp/destructive')
        ->assertJsonFragment(['mcp-delegated'])
        ->assertJsonFragment(['mcp-effect-scoped']);
    McpDelegatedEnforcement::assertHonors(
        DrawServer::class,
        drawMcpTools(),
        role: ConsoleRole::Member,
        negativeAuthorization: static function (
            ActingPrincipal $first,
            ActingPrincipal $second,
            Closure $invoke,
        ) use ($board): void {
            expect($first->identifier())->not->toBe($second->identifier());
            $response = $invoke(ListBoardsTool::class);
            expect($response)->toBeInstanceOf(ResponseFactory::class)
                ->and($response->getStructuredContent()['boards'] ?? [])->toBe([]);

            $refused = $invoke(GetBoardTool::class, ['id' => $board->id]);
            expect($refused)->toBeInstanceOf(Response::class)
                ->and($refused->isError())->toBeTrue();
        },
        arguments: delegatedMcpArguments($board->id),
    );

    expect(Board::query()->where('title', 'Delegated created board')->sole()->creator_id)->toBe($primary->id);
});

it('fails delegated projection closed without an exact active admitted local BfC user', function (string $case): void {
    if ($case !== 'no managed connection') {
        enableDelegatedMcpProjection();
    }

    if (in_array($case, ['inactive user', 'offboarded user'], true)) {
        $user = mcpUser('Contained delegated user', scalpelsId: 'bfc-enforcement-primary');

        if ($case === 'inactive user') {
            $user->forceFill(['status' => 'deactivated', 'deactivated_at' => now()])->save();
        } else {
            OffboardedSubject::query()->create([
                'subject_type' => SubjectType::UserPrincipal,
                'subject_ref' => 'draw-user:'.$user->id,
                'user_id' => (string) $user->id,
                'offboarded_at' => now(),
            ]);
        }
    } elseif ($case === 'no managed connection') {
        mcpUser('Unbound delegated user', scalpelsId: 'bfc-enforcement-primary');
    }

    $result = McpDelegatedEnforcement::probe(
        DrawServer::class,
        arguments: delegatedMcpArguments((string) Str::uuid7()),
    );

    expect($result['tools'])->toHaveCount(10)
        ->and($result['invoked'])->toHaveCount(10)
        ->and($result['errors'])->toHaveCount(10);
})->with([
    'no managed connection',
    'no exact user',
    'inactive user',
    'offboarded user',
]);

it('checks the exact required ability inside every tool handle', function (): void {
    $user = mcpUser('Ability user');
    $board = Board::query()->create([
        'creator_id' => $user->id,
        'title' => 'Ability board',
        'scene' => [
            'elements' => [['id' => 'seed', 'type' => 'rectangle', 'version' => 1, 'versionNonce' => 1]],
            'appState' => [],
            'files' => [],
        ],
    ]);
    $missing = mcpCredential($user, null);
    $calls = [
        ['/mcp', 'list_boards', []],
        ['/mcp', 'get_board', ['id' => $board->id]],
        ['/mcp/write', 'create_board', ['title' => 'Refused']],
        ['/mcp/write', 'add_elements', ['id' => $board->id, 'elements' => [['type' => 'text', 'text' => 'No']]]],
        ['/mcp/write', 'update_elements', ['id' => $board->id, 'elements' => [['id' => 'seed', 'x' => 1]]]],
        ['/mcp/destructive', 'delete_elements', ['id' => $board->id, 'element_ids' => ['seed']]],
        ['/mcp/destructive', 'clear_board', ['id' => $board->id]],
        ['/mcp/write', 'add_mermaid', ['id' => $board->id, 'mermaid' => 'flowchart LR; A --> B']],
        ['/mcp/write', 'add_slide', ['id' => $board->id, 'title' => 'No', 'elements' => []]],
        ['/mcp/write', 'share_board_link', ['id' => $board->id]],
    ];

    foreach ($calls as $index => [$path, $tool, $arguments]) {
        callMcp($path, $tool, $arguments, $missing, $index + 1)
            ->assertOk()
            ->assertJsonPath('result.isError', true);
    }

    $prefixRead = prefixSimilarMcpCredential($user, 'mcp:rea');
    callMcp('/mcp', 'list_boards', [], $prefixRead)->assertOk()->assertJsonPath('result.isError', true);

    $prefixAdmin = prefixSimilarMcpCredential($user, 'mcp:admi');
    callMcp('/mcp/write', 'create_board', ['title' => 'Still refused'], $prefixAdmin)
        ->assertOk()
        ->assertJsonPath('result.isError', true);

    expect(Board::query()->count())->toBe(1);
});

it('invokes every tool through its BfC effect door for an authorized creator', function (): void {
    Event::fake([BoardUpdated::class]);
    $user = mcpUser('MCP creator');
    $read = mcpCredential($user, [OperatorAbility::McpRead->value]);
    $admin = mcpCredential($user, [OperatorAbility::McpAdmin->value]);
    $shapeId = (string) Str::uuid7();

    callMcp('/mcp', 'list_boards', [], $read)->assertOk()->assertJsonPath('result.structuredContent.boards', []);
    callMcp('/mcp/write', 'create_board', ['title' => 'MCP full path'], $admin)->assertOk();
    $board = Board::query()->where('title', 'MCP full path')->sole();

    callMcp('/mcp', 'get_board', ['id' => $board->id], $read)
        ->assertOk()
        ->assertJsonPath('result.structuredContent.id', $board->id);
    callMcp('/mcp/write', 'add_elements', [
        'id' => $board->id,
        'elements' => [['id' => $shapeId, 'type' => 'rectangle', 'label' => 'Added', 'x' => 0, 'y' => 0, 'w' => 120, 'h' => 80]],
    ], $admin)->assertOk();
    callMcp('/mcp/write', 'update_elements', [
        'id' => $board->id,
        'elements' => [['id' => $shapeId, 'backgroundColor' => '#ffec99']],
    ], $admin)->assertOk();
    $updated = collect($board->fresh()->scene['elements'])->firstWhere('id', $shapeId);
    expect($updated['backgroundColor'])->toBe('#ffec99')
        ->and($updated['version'])->toBe(2);
    callMcp('/mcp/write', 'add_mermaid', [
        'id' => $board->id,
        'mermaid' => 'flowchart LR; A[Draft] --> B[Ship]',
    ], $admin)->assertOk();
    callMcp('/mcp/write', 'add_slide', [
        'id' => $board->id,
        'title' => 'Summary',
        'elements' => [['type' => 'text', 'text' => 'Takeaway']],
    ], $admin)->assertOk();
    callMcp('/mcp/destructive', 'delete_elements', [
        'id' => $board->id,
        'element_ids' => [$shapeId],
    ], $admin)->assertOk();
    callMcp('/mcp/destructive', 'clear_board', ['id' => $board->id], $admin)->assertOk();
    $shareResponse = callMcp('/mcp/write', 'share_board_link', [
        'id' => $board->id,
        'expires_in_minutes' => 30,
    ], $admin)->assertOk();

    $board->refresh();
    expect($board->creator_id)->toBe($user->id)
        ->and($board->revision)->toBe(6)
        ->and($board->scene['elements'])->toBe([])
        ->and($shareResponse->json('result.structuredContent.url'))->toBeString()
        ->and($board->shares()->where('kind', BoardShareKind::Link->value)->count())->toBe(1);
    Event::assertDispatchedTimes(BoardUpdated::class, 6);
});

it('lists and gets only own direct and team shared boards', function (): void {
    $creator = mcpUser('MCP list creator');
    $recipient = mcpUser('MCP list recipient');
    $own = mcpBoard($recipient, 'Recipient own board');
    $direct = mcpBoard($creator, 'Direct shared board');
    $team = mcpBoard($creator, 'Team shared board');
    $private = mcpBoard($creator, 'Creator private board');
    $direct->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $recipient->id,
        'access' => ShareAccess::View,
    ]);
    $team->shares()->create([
        'kind' => BoardShareKind::Team,
        'access' => ShareAccess::View,
    ]);
    $read = mcpCredential($recipient, [OperatorAbility::McpRead->value]);

    $listed = callMcp('/mcp', 'list_boards', [], $read)->assertOk();
    $ids = collect($listed->json('result.structuredContent.boards'))->pluck('id')->all();
    expect($ids)->toContain($own->id)
        ->toContain($direct->id)
        ->toContain($team->id)
        ->not->toContain($private->id);

    foreach ([$own, $direct, $team] as $board) {
        callMcp('/mcp', 'get_board', ['id' => $board->id], $read)
            ->assertOk()
            ->assertJsonPath('result.structuredContent.id', $board->id);
    }
});

/** @return array<string, array{0: string, 1: string, 2: Closure(Board): array<string, mixed>}> */
function mcpWriteFamilies(): array
{
    return [
        'add' => ['/mcp/write', 'add_elements', fn (Board $board): array => ['id' => $board->id, 'elements' => [['type' => 'text', 'text' => 'Added']]]],
        'update' => ['/mcp/write', 'update_elements', fn (Board $board): array => ['id' => $board->id, 'elements' => [['id' => 'seed', 'x' => 25]]]],
        'delete' => ['/mcp/destructive', 'delete_elements', fn (Board $board): array => ['id' => $board->id, 'element_ids' => ['seed']]],
        'clear' => ['/mcp/destructive', 'clear_board', fn (Board $board): array => ['id' => $board->id]],
        'mermaid' => ['/mcp/write', 'add_mermaid', fn (Board $board): array => ['id' => $board->id, 'mermaid' => 'flowchart LR; A --> B']],
        'slide' => ['/mcp/write', 'add_slide', fn (Board $board): array => ['id' => $board->id, 'title' => 'Shared slide', 'elements' => []]],
    ];
}

function mcpBoard(User $creator, string $title): Board
{
    return Board::query()->create([
        'creator_id' => $creator->id,
        'title' => $title,
        'scene' => [
            'elements' => [['id' => 'seed', 'type' => 'rectangle', 'version' => 1, 'versionNonce' => 1]],
            'appState' => [],
            'files' => [],
        ],
    ]);
}

it('enforces direct and team view or edit access on every MCP write family', function (): void {
    $creator = mcpUser('Write family creator');
    $directViewer = mcpUser('Direct MCP viewer');
    $directEditor = mcpUser('Direct MCP editor');
    $teamMember = mcpUser('Team MCP member');
    $viewToken = mcpCredential($directViewer, [OperatorAbility::McpAdmin->value]);
    $editToken = mcpCredential($directEditor, [OperatorAbility::McpAdmin->value]);
    $teamToken = mcpCredential($teamMember, [OperatorAbility::McpAdmin->value]);

    foreach (mcpWriteFamilies() as $label => [$path, $tool, $arguments]) {
        $direct = mcpBoard($creator, 'Direct '.$label);
        $direct->shares()->create([
            'kind' => BoardShareKind::User,
            'recipient_user_id' => $directViewer->id,
            'access' => ShareAccess::View,
        ]);
        $direct->shares()->create([
            'kind' => BoardShareKind::User,
            'recipient_user_id' => $directEditor->id,
            'access' => ShareAccess::Edit,
        ]);

        callMcp($path, $tool, $arguments($direct), $viewToken)
            ->assertOk()
            ->assertJsonPath('result.isError', true);
        callMcp($path, $tool, $arguments($direct), $editToken)
            ->assertOk()
            ->assertJsonPath('result.isError', false);

        $teamView = mcpBoard($creator, 'Team view '.$label);
        $teamView->shares()->create([
            'kind' => BoardShareKind::Team,
            'access' => ShareAccess::View,
        ]);
        callMcp($path, $tool, $arguments($teamView), $teamToken)
            ->assertOk()
            ->assertJsonPath('result.isError', true);

        $teamEdit = mcpBoard($creator, 'Team edit '.$label);
        $teamEdit->shares()->create([
            'kind' => BoardShareKind::Team,
            'access' => ShareAccess::Edit,
        ]);
        callMcp($path, $tool, $arguments($teamEdit), $teamToken)
            ->assertOk()
            ->assertJsonPath('result.isError', false);
    }
});

it('keeps private boards out of every admitted role MCP read and write surface', function (UserRole $role): void {
    $creator = mcpUser('Private MCP creator');
    $unrelated = mcpUser('Unrelated MCP '.$role->value, $role);
    $board = mcpBoard($creator, 'Private role board');
    $read = mcpCredential($unrelated, [OperatorAbility::McpRead->value]);
    $admin = mcpCredential($unrelated, [OperatorAbility::McpAdmin->value]);

    callMcp('/mcp', 'list_boards', [], $read)
        ->assertOk()
        ->assertJsonMissing(['id' => $board->id]);
    callMcp('/mcp', 'get_board', ['id' => $board->id], $read)
        ->assertOk()
        ->assertJsonPath('result.isError', true);

    foreach (mcpWriteFamilies() as [$path, $tool, $arguments]) {
        callMcp($path, $tool, $arguments($board), $admin)
            ->assertOk()
            ->assertJsonPath('result.isError', true);
    }
})->with(UserRole::cases());

it('returns the same refusal for malformed and inaccessible board ids without querying malformed UUIDs', function (): void {
    $creator = mcpUser('UUID owner');
    $other = mcpUser('UUID caller');
    $board = mcpBoard($creator, 'UUID private board');
    $read = mcpCredential($other, [OperatorAbility::McpRead->value]);
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $malformed = callMcp('/mcp', 'get_board', ['id' => 'not-a-uuid'], $read)->assertOk();
    expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, '"boards"')))->toBeFalse();

    $inaccessible = callMcp('/mcp', 'get_board', ['id' => $board->id], $read)->assertOk();
    expect($malformed->json('result'))->toBe($inaccessible->json('result'));
});

it('makes share revocation win on the next MCP write', function (): void {
    $creator = mcpUser('Revocation creator');
    $editor = mcpUser('Revocation editor');
    $board = mcpBoard($creator, 'Revocable MCP board');
    $share = $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $editor->id,
        'access' => ShareAccess::Edit,
    ]);
    $admin = mcpCredential($editor, [OperatorAbility::McpAdmin->value]);

    callMcp('/mcp/write', 'add_elements', [
        'id' => $board->id,
        'elements' => [['type' => 'text', 'text' => 'Before revoke']],
    ], $admin)->assertOk()->assertJsonPath('result.isError', false);

    $share->delete();
    callMcp('/mcp/write', 'add_elements', [
        'id' => $board->id,
        'elements' => [['type' => 'text', 'text' => 'After revoke']],
    ], $admin)->assertOk()->assertJsonPath('result.isError', true);
});

it('allows only the creator to create an existing-model signed link', function (): void {
    $creator = mcpUser('Link MCP creator');
    $editor = mcpUser('Link MCP editor');
    $viewer = mcpUser('Link MCP viewer');
    $board = mcpBoard($creator, 'Link MCP board');
    $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $editor->id,
        'access' => ShareAccess::Edit,
    ]);
    $board->shares()->create([
        'kind' => BoardShareKind::User,
        'recipient_user_id' => $viewer->id,
        'access' => ShareAccess::View,
    ]);

    foreach ([$editor, $viewer] as $recipient) {
        callMcp(
            '/mcp/write',
            'share_board_link',
            ['id' => $board->id],
            mcpCredential($recipient, [OperatorAbility::McpAdmin->value]),
        )->assertOk()->assertJsonPath('result.isError', true);
    }

    $ownerResponse = callMcp(
        '/mcp/write',
        'share_board_link',
        ['id' => $board->id, 'expires_in_minutes' => 15],
        mcpCredential($creator, [OperatorAbility::McpAdmin->value]),
    )->assertOk();
    $share = $board->shares()->where('kind', BoardShareKind::Link->value)->sole();

    expect($ownerResponse->json('result.structuredContent.url'))->toBe($share->signedUrl())
        ->and($share->access)->toBeNull()
        ->and($share->expires_at)->not->toBeNull();
});

it('rolls back invalid or unsupported Mermaid without a revision signal', function (): void {
    Event::fake([BoardUpdated::class]);
    $creator = mcpUser('Mermaid rollback creator');
    $board = mcpBoard($creator, 'Mermaid rollback board');
    $admin = mcpCredential($creator, [OperatorAbility::McpAdmin->value]);
    $before = $board->scene;

    callMcp('/mcp/write', 'add_mermaid', [
        'id' => $board->id,
        'mermaid' => 'sequenceDiagram; Alice->>Bob: Unsupported',
    ], $admin)
        ->assertOk()
        ->assertJsonPath('result.isError', true);

    expect($board->fresh()->scene)->toBe($before)
        ->and($board->fresh()->revision)->toBe(0);
    Event::assertNotDispatched(BoardUpdated::class);
});

it('emits exactly one compact after-commit revision event for each successful MCP mutation and none for refusal', function (): void {
    Event::fake([BoardUpdated::class]);
    $creator = mcpUser('MCP event creator');
    $viewer = mcpUser('MCP event viewer');
    $ownerToken = mcpCredential($creator, [OperatorAbility::McpAdmin->value]);
    $viewerToken = mcpCredential($viewer, [OperatorAbility::McpAdmin->value]);
    $expectedEvents = 0;

    foreach (mcpWriteFamilies() as $label => [$path, $tool, $arguments]) {
        $board = mcpBoard($creator, 'MCP event '.$label);
        $board->shares()->create([
            'kind' => BoardShareKind::User,
            'recipient_user_id' => $viewer->id,
            'access' => ShareAccess::View,
        ]);

        callMcp($path, $tool, $arguments($board), $viewerToken)
            ->assertOk()
            ->assertJsonPath('result.isError', true);
        Event::assertDispatchedTimes(BoardUpdated::class, $expectedEvents);

        callMcp($path, $tool, $arguments($board), $ownerToken)
            ->assertOk()
            ->assertJsonPath('result.isError', false);
        $expectedEvents++;
        Event::assertDispatchedTimes(BoardUpdated::class, $expectedEvents);
    }

    Event::assertDispatched(BoardUpdated::class, function (BoardUpdated $event): bool {
        expect(array_keys($event->broadcastWith()))->toBe(['revision'])
            ->and($event->revision)->toBeGreaterThan(0);

        return true;
    });
});

it('bounds structured scene inputs and keeps deletion behind the destructive door', function (): void {
    $creator = mcpUser('Bounded input creator');
    $board = mcpBoard($creator, 'Bounded input board');
    $admin = mcpCredential($creator, [OperatorAbility::McpAdmin->value]);
    $originalScene = $board->scene;

    foreach ([
        ['id' => 'seed', 'isDeleted' => true],
        ['id' => 'seed', 'deleted' => true],
        ['id' => 'seed', 'customState' => ['nested' => ['delete' => true]]],
    ] as $patch) {
        callMcp('/mcp/write', 'update_elements', [
            'id' => $board->id,
            'elements' => [$patch],
        ], $admin)->assertOk()->assertJsonPath('result.isError', true);

        expect($board->fresh()->scene)->toBe($originalScene)
            ->and($board->fresh()->revision)->toBe(0);
    }

    $tooManyPoints = array_fill(0, 101, [0, 0]);
    callMcp('/mcp/write', 'add_elements', [
        'id' => $board->id,
        'elements' => [['type' => 'arrow', 'points' => $tooManyPoints]],
    ], $admin)->assertOk()->assertJsonPath('result.isError', true);
    expect($board->fresh()->scene)->toBe($originalScene)
        ->and($board->fresh()->revision)->toBe(0);
    callMcp('/mcp/write', 'update_elements', [
        'id' => $board->id,
        'elements' => [['id' => 'seed', 'text' => str_repeat('x', 4001)]],
    ], $admin)->assertOk()->assertJsonPath('result.isError', true);
    expect($board->fresh()->scene)->toBe($originalScene)
        ->and($board->fresh()->revision)->toBe(0);
    callMcp('/mcp/write', 'add_slide', [
        'id' => $board->id,
        'title' => 'Too deep',
        'elements' => [[
            'type' => 'arrow',
            'start' => ['id' => 'seed', 'extra' => ['nested' => ['escape' => true]]],
        ]],
    ], $admin)->assertOk()->assertJsonPath('result.isError', true);
    expect($board->fresh()->scene)->toBe($originalScene)
        ->and($board->fresh()->revision)->toBe(0);

    foreach ([
        ['elementId' => 'seed', 'id' => ['nested' => ['escape' => true]]],
        ['elementId' => 'seed', 'id' => str_repeat('x', 1_048_576)],
    ] as $binding) {
        callMcp('/mcp/write', 'update_elements', [
            'id' => $board->id,
            'elements' => [['id' => 'seed', 'startBinding' => $binding]],
        ], $admin)->assertOk()->assertJsonPath('result.isError', true);

        expect($board->fresh()->scene)->toBe($originalScene)
            ->and($board->fresh()->revision)->toBe(0);
    }

    $idAliasArrowId = (string) Str::uuid7();
    $elementIdAliasArrowId = (string) Str::uuid7();
    callMcp('/mcp/write', 'add_elements', [
        'id' => $board->id,
        'elements' => [
            [
                'id' => $idAliasArrowId,
                'type' => 'arrow',
                'points' => [[0, 0], [120, 40]],
                'start' => ['id' => 'seed', 'focus' => 0, 'gap' => 8],
                'label' => 'Id alias arrow',
            ],
            [
                'id' => $elementIdAliasArrowId,
                'type' => 'arrow',
                'points' => [[0, 0], [80, 60]],
                'startBinding' => ['elementId' => 'seed', 'focus' => 0.5, 'gap' => 4],
                'label' => 'Element id alias arrow',
            ],
        ],
    ], $admin)->assertOk()->assertJsonPath('result.isError', false);
    $addedElements = collect($board->fresh()->scene['elements']);
    expect($addedElements->firstWhere('id', $idAliasArrowId)['startBinding']['elementId'])->toBe('seed')
        ->and($addedElements->firstWhere('id', $elementIdAliasArrowId)['startBinding']['elementId'])->toBe('seed');
    callMcp('/mcp/write', 'update_elements', [
        'id' => $board->id,
        'elements' => [['id' => 'seed', 'x' => 25, 'backgroundColor' => '#ffec99', 'locked' => true]],
    ], $admin)->assertOk()->assertJsonPath('result.isError', false);
    callMcp('/mcp/write', 'add_slide', [
        'id' => $board->id,
        'title' => 'Bounded slide',
        'elements' => [['type' => 'text', 'text' => 'Bounded content', 'x' => 10, 'y' => 20]],
    ], $admin)->assertOk()->assertJsonPath('result.isError', false);

    callMcp('/mcp/destructive', 'delete_elements', [
        'id' => $board->id,
        'element_ids' => ['seed'],
    ], $admin)->assertOk()->assertJsonPath('result.isError', false);

    $deleted = collect($board->fresh()->scene['elements'])->firstWhere('id', 'seed');
    expect($deleted['isDeleted'])->toBeTrue()
        ->and($board->fresh()->revision)->toBe(4);
});

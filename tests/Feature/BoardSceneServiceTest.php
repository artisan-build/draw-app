<?php

declare(strict_types=1);

use App\Models\Board;
use App\Services\BoardSceneService;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Support\Facades\DB;

function sceneBoard(array $scene = []): Board
{
    $creator = User::query()->create([
        'name' => 'Scene creator',
        'email' => 'scene-'.strtolower((string) str()->uuid()).'@example.test',
    ]);

    return Board::query()->create([
        'creator_id' => $creator->id,
        'title' => 'Scene test board',
        'scene' => $scene === [] ? null : $scene,
    ]);
}

it('locks the board row and merges elements by version then version nonce', function (): void {
    $board = sceneBoard([
        'elements' => [
            ['id' => 'shared', 'type' => 'rectangle', 'version' => 2, 'versionNonce' => 20],
            ['id' => 'untouched', 'type' => 'ellipse', 'version' => 1, 'versionNonce' => 5],
        ],
        'appState' => [],
        'files' => [],
    ]);
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $scenes = resolve(BoardSceneService::class);

    $board = $scenes->addElements($board->creator, $board, [
        ['id' => 'shared', 'type' => 'diamond', 'version' => 1, 'versionNonce' => 999],
    ]);
    expect(collect($board->scene['elements'])->firstWhere('id', 'shared')['type'])->toBe('rectangle');

    $board = $scenes->addElements($board->creator, $board, [
        ['id' => 'shared', 'type' => 'diamond', 'version' => 2, 'versionNonce' => 19],
    ]);
    expect(collect($board->scene['elements'])->firstWhere('id', 'shared')['type'])->toBe('rectangle');

    $board = $scenes->addElements($board->creator, $board, [
        ['id' => 'shared', 'type' => 'diamond', 'version' => 2, 'versionNonce' => 21],
    ]);
    expect(collect($board->scene['elements'])->firstWhere('id', 'shared')['type'])->toBe('diamond');

    $board = $scenes->addElements($board->creator, $board, [
        ['id' => 'shared', 'type' => 'text', 'version' => 3, 'versionNonce' => 1],
    ]);

    expect(collect($board->scene['elements'])->firstWhere('id', 'shared')['type'])->toBe('text')
        ->and(collect($board->scene['elements'])->pluck('id')->all())->toContain('untouched')
        ->and($board->revision)->toBe(4)
        ->and(collect($queries)->contains(fn (string $sql): bool => str_contains(strtolower($sql), 'for update')))->toBeTrue();
});

it('updates deletes and clears elements while advancing the revision', function (): void {
    $board = sceneBoard([
        'elements' => [
            ['id' => 'first', 'type' => 'rectangle', 'version' => 1, 'versionNonce' => 10],
            ['id' => 'second', 'type' => 'ellipse', 'version' => 4, 'versionNonce' => 20],
        ],
        'appState' => [],
        'files' => [],
    ]);
    $scenes = resolve(BoardSceneService::class);

    $board = $scenes->updateElements($board->creator, $board, [['id' => 'first', 'x' => 42]]);
    $updated = collect($board->scene['elements'])->firstWhere('id', 'first');

    expect($updated['x'])->toBe(42)
        ->and($updated['version'])->toBe(2)
        ->and($board->revision)->toBe(1);

    $board = $scenes->deleteElements($board->creator, $board, ['second']);
    $deleted = collect($board->scene['elements'])->firstWhere('id', 'second');

    expect($deleted['isDeleted'])->toBeTrue()
        ->and($deleted['version'])->toBe(5)
        ->and($board->revision)->toBe(2);

    $board = $scenes->clear($board->creator, $board);

    expect($board->scene['elements'])->toBe([])
        ->and($board->revision)->toBe(3);
});

it('converts skeletons into Excalidraw elements in PHP', function (): void {
    $board = sceneBoard();

    $board = resolve(BoardSceneService::class)->addSkeletons($board->creator, $board, [
        ['id' => 'box', 'type' => 'rectangle', 'label' => 'Start', 'x' => 10, 'y' => 20, 'w' => 240, 'h' => 120],
        ['type' => 'arrow', 'start' => ['id' => 'box'], 'x' => 250, 'y' => 80, 'w' => 100, 'h' => 0],
    ]);

    $elements = collect($board->scene['elements']);
    $box = $elements->firstWhere('type', 'rectangle');
    $arrow = $elements->firstWhere('type', 'arrow');

    expect($box['width'])->toBe(240)
        ->and($box['height'])->toBe(120)
        ->and($box['boundElements'])->toHaveCount(1)
        ->and($elements->firstWhere('containerId', $box['id'])['text'])->toBe('Start')
        ->and($arrow['startBinding']['elementId'])->toBe($box['id'])
        ->and($board->revision)->toBe(1);
});

it('converts Mermaid flowcharts into Excalidraw nodes and arrows in PHP', function (): void {
    $board = sceneBoard();

    $board = resolve(BoardSceneService::class)->addMermaid(
        $board->creator,
        $board,
        'flowchart LR; Start[Draft] --> Review{Ready}; Other[Queue] --> Done[Ship]',
    );

    $elements = collect($board->scene['elements']);

    expect($elements->where('type', 'arrow'))->toHaveCount(2)
        ->and($elements->where('type', 'rectangle')->count())->toBeGreaterThanOrEqual(3)
        ->and($elements->where('type', 'diamond'))->toHaveCount(1)
        ->and($elements->pluck('text')->filter()->all())->toContain('Draft', 'Ready', 'Ship')
        ->and($board->revision)->toBe(1);
});

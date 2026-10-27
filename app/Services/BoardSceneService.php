<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Board;
use Illuminate\Support\Facades\DB;

/** @phpstan-import-type Scene from Board */
class BoardSceneService
{
    public function __construct(private readonly ExcalidrawConverter $converter) {}

    /** @param array<int, array<string, mixed>> $skeletons */
    public function addSkeletons(Board $board, array $skeletons): Board
    {
        return $this->addElements($board, $this->converter->convert($skeletons));
    }

    public function addMermaid(Board $board, string $mermaid): Board
    {
        $scene = $board->scene;
        $visible = array_filter($scene['elements'], fn (array $element): bool => ! ($element['isDeleted'] ?? false));
        $maxY = $visible === [] ? 0 : max(array_map(
            fn (array $element): float => (float) ($element['y'] ?? 0) + (float) ($element['height'] ?? 0),
            $visible,
        ));

        return $this->addElements($board, $this->converter->mermaid($mermaid, 0, $maxY + 160));
    }

    /** @param array<int, array<string, mixed>> $elements */
    public function addElements(Board $board, array $elements): Board
    {
        return $this->mutate($board, function (array $scene) use ($elements): array {
            $scene['elements'] = $this->mergeElements($scene['elements'], $elements);

            return $scene;
        });
    }

    /** @param array<int, array<string, mixed>> $updates */
    public function updateElements(Board $board, array $updates): Board
    {
        return $this->mutate($board, function (array $scene) use ($updates): array {
            $byId = [];
            foreach ($scene['elements'] as $element) {
                $elementId = $element['id'] ?? null;
                if (is_string($elementId)) {
                    $byId[$elementId] = $element;
                }
            }

            foreach ($updates as $update) {
                $id = $update['id'] ?? null;
                if (! is_string($id) || ! array_key_exists($id, $byId)) {
                    continue;
                }

                $current = $byId[$id];
                $byId[$id] = [
                    ...$current,
                    ...$update,
                    'id' => $id,
                    'version' => ((int) ($current['version'] ?? 0)) + 1,
                    'versionNonce' => random_int(1, 2_147_483_646),
                    'updated' => now()->getTimestampMs(),
                ];
            }
            $scene['elements'] = array_values($byId);

            return $scene;
        });
    }

    /** @param array<int, string> $ids */
    public function deleteElements(Board $board, array $ids): Board
    {
        return $this->updateElements(
            $board,
            array_map(fn (string $id): array => ['id' => $id, 'isDeleted' => true], $ids),
        );
    }

    public function clear(Board $board): Board
    {
        return $this->mutate($board, fn (array $scene): array => [...$scene, 'elements' => []]);
    }

    /** @param array<int, array<string, mixed>> $skeletons */
    public function addSlide(Board $board, string $title, array $skeletons): Board
    {
        $scene = $board->scene;
        $frames = array_values(array_filter(
            $scene['elements'],
            fn (array $element): bool => ($element['type'] ?? null) === 'frame' && ! ($element['isDeleted'] ?? false),
        ));
        $x = $frames === [] ? 0 : max(array_map(
            fn (array $frame): float => (float) $frame['x'] + (float) $frame['width'],
            $frames,
        )) + 240;
        $frame = $this->converter->convert([[
            'type' => 'frame',
            'name' => $title,
            'x' => $x,
            'y' => 0,
            'w' => 1920,
            'h' => 1080,
            'strokeColor' => '#868e96',
        ]])[0];
        $titleElement = $this->converter->convert([[
            'type' => 'text',
            'text' => $title,
            'x' => $x + 80,
            'y' => 60,
            'w' => 1760,
            'h' => 80,
        ]])[0];
        $titleElement['frameId'] = $frame['id'];
        $children = $this->converter->convert($skeletons, $x + 80, 170, $frame['id']);

        return $this->addElements($board, [$frame, $titleElement, ...$children]);
    }

    /** @param array{elements: array<int, array<string, mixed>>, appState?: array<string, mixed>, files?: array<string, mixed>} $incoming */
    public function saveEditorScene(Board $board, array $incoming): Board
    {
        return $this->mutate($board, fn (array $scene): array => [
            'elements' => $this->mergeElements($scene['elements'], $incoming['elements']),
            'appState' => array_intersect_key(
                $incoming['appState'] ?? [],
                array_flip(['viewBackgroundColor', 'gridSize', 'gridStep', 'gridModeEnabled']),
            ),
            'files' => [...$scene['files'], ...($incoming['files'] ?? [])],
        ]);
    }

    /** @param callable(Scene): Scene $callback */
    private function mutate(Board $board, callable $callback): Board
    {
        return DB::transaction(function () use ($board, $callback): Board {
            $locked = Board::query()->lockForUpdate()->whereKey($board->id)->firstOrFail();
            $locked->scene = $callback($locked->scene);
            $locked->revision++;
            $locked->save();
            $locked->refresh();

            return $locked;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<int, array<string, mixed>>
     */
    private function mergeElements(array $existing, array $incoming): array
    {
        $merged = collect($existing)->keyBy('id');
        foreach ($incoming as $element) {
            $id = $element['id'] ?? null;
            if (! is_string($id)) {
                continue;
            }

            $current = $merged->get($id);
            if ($current === null || $this->wins($element, $current)) {
                $merged->put($id, $element);
            }
        }

        return $merged->values()->all();
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $current
     */
    private function wins(array $incoming, array $current): bool
    {
        $incomingVersion = (int) ($incoming['version'] ?? 1);
        $currentVersion = (int) ($current['version'] ?? 1);

        return $incomingVersion > $currentVersion
            || ($incomingVersion === $currentVersion
                && (int) ($incoming['versionNonce'] ?? 0) >= (int) ($current['versionNonce'] ?? 0));
    }
}

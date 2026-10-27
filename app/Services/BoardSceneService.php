<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BoardShareKind;
use App\Events\BoardUpdated;
use App\Models\Board;
use ArtisanBuild\BuiltForCloud\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/** @phpstan-import-type Scene from Board */
class BoardSceneService
{
    public function __construct(
        private readonly ExcalidrawConverter $converter,
        private readonly BoardAccess $access,
    ) {}

    /** @param array<int, array<string, mixed>> $skeletons */
    public function addSkeletons(User $actor, Board $board, array $skeletons): Board
    {
        return $this->addElements($actor, $board, $this->converter->convert($skeletons));
    }

    public function addMermaid(User $actor, Board $board, string $mermaid): Board
    {
        return $this->mutate($actor, $board, function (array $scene) use ($mermaid): array {
            $visible = array_filter($scene['elements'], fn (array $element): bool => ! ($element['isDeleted'] ?? false));
            $maxY = $visible === [] ? 0 : max(array_map(
                fn (array $element): float => (float) ($element['y'] ?? 0) + (float) ($element['height'] ?? 0),
                $visible,
            ));
            $elements = $this->converter->mermaid($mermaid, 0, $maxY + 160);
            $scene['elements'] = $this->mergeElements($scene['elements'], $elements);

            return $scene;
        });
    }

    /** @param array<int, array<string, mixed>> $elements */
    public function addElements(User $actor, Board $board, array $elements): Board
    {
        return $this->mutate($actor, $board, function (array $scene) use ($elements): array {
            $scene['elements'] = $this->mergeElements($scene['elements'], $elements);

            return $scene;
        });
    }

    /** @param array<int, array<string, mixed>> $updates */
    public function updateElements(User $actor, Board $board, array $updates): Board
    {
        return $this->mutate($actor, $board, function (array $scene) use ($updates): array {
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
    public function deleteElements(User $actor, Board $board, array $ids): Board
    {
        return $this->updateElements(
            $actor,
            $board,
            array_map(fn (string $id): array => ['id' => $id, 'isDeleted' => true], $ids),
        );
    }

    public function clear(User $actor, Board $board): Board
    {
        return $this->mutate($actor, $board, fn (array $scene): array => [...$scene, 'elements' => []]);
    }

    /** @param array<int, array<string, mixed>> $skeletons */
    public function addSlide(User $actor, Board $board, string $title, array $skeletons): Board
    {
        return $this->mutate($actor, $board, function (array $scene) use ($title, $skeletons): array {
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
            $scene['elements'] = $this->mergeElements($scene['elements'], [$frame, $titleElement, ...$children]);

            return $scene;
        });
    }

    /** @param array{elements: array<int, array<string, mixed>>, appState?: array<string, mixed>, files?: array<string, mixed>} $incoming */
    public function saveEditorScene(User $actor, Board $board, array $incoming, int $expectedRevision): Board
    {
        return $this->mutate($actor, $board, fn (array $scene): array => [
            'elements' => $incoming['elements'],
            'appState' => array_intersect_key(
                $incoming['appState'] ?? [],
                array_flip(['viewBackgroundColor', 'gridSize', 'gridStep', 'gridModeEnabled']),
            ),
            'files' => $incoming['files'] ?? [],
        ], $expectedRevision);
    }

    /** @param callable(Scene): Scene $callback */
    private function mutate(User $actor, Board $board, callable $callback, ?int $expectedRevision = null): Board
    {
        return DB::transaction(function () use ($actor, $board, $callback, $expectedRevision): Board {
            $locked = Board::query()->lockForUpdate()->whereKey($board->id)->firstOrFail();
            abort_unless($this->access->editableBy($actor)->whereKey($locked->id)->exists(), 404);
            abort_if(
                $expectedRevision !== null && $locked->revision !== $expectedRevision,
                409,
                'The board changed before this edit could be saved.',
            );
            $locked->scene = $callback($locked->scene);
            $locked->revision++;
            $locked->save();
            $locked->refresh();

            $linkShareIds = $locked->shares()
                ->where('kind', BoardShareKind::Link->value)
                ->where(fn ($query) => $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()))
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all();

            DB::afterCommit(function () use ($locked, $linkShareIds): void {
                try {
                    $event = new BoardUpdated($locked->id, $locked->revision, $linkShareIds);
                    $event->dontBroadcastToCurrentUser();
                    event($event);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

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

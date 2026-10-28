<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use App\Models\Board;

trait PresentsBoards
{
    /** @return array<string, mixed> */
    protected function boardPayload(Board $board, bool $includeScene = true): array
    {
        $payload = [
            'id' => $board->id,
            'title' => $board->title,
            'revision' => $board->revision,
            'updated_at' => $board->updated_at?->toISOString(),
        ];

        if ($includeScene) {
            $payload['elements'] = collect($board->scene['elements'])
                ->reject(fn (array $element): bool => (bool) ($element['isDeleted'] ?? false))
                ->values()
                ->all();
        }

        return $payload;
    }
}

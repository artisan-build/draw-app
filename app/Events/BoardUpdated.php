<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class BoardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param array<int, string> $linkShareIds */
    public function __construct(
        public readonly string $boardId,
        public readonly int $revision,
        private readonly array $linkShareIds = [],
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('boards.'.$this->boardId),
            ...array_map(
                fn (string $shareId): Channel => new Channel('link-shares.'.$shareId),
                $this->linkShareIds,
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'board.updated';
    }

    /** @return array{revision: int} */
    public function broadcastWith(): array
    {
        return ['revision' => $this->revision];
    }
}

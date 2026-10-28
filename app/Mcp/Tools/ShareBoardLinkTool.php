<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\BoardShareKind;
use App\Mcp\Tools\Concerns\AccessesBoards;
use App\Services\BoardShareService;
use ArtisanBuild\BuiltForCloud\Mcp\AdvertisesToolClassification;
use ArtisanBuild\BuiltForCloud\Mcp\AdvertisesToolEffect;
use ArtisanBuild\BuiltForCloud\Mcp\Classification;
use ArtisanBuild\BuiltForCloud\Mcp\Effect;
use ArtisanBuild\BuiltForCloud\Mcp\RespectsEffectCeiling;
use ArtisanBuild\BuiltForCloud\Mcp\ToolClassification;
use ArtisanBuild\BuiltForCloud\Mcp\ToolEffect;
use ArtisanBuild\BuiltForCloud\OperatorAbility;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('share_board_link')]
#[Description('Create an existing-model revocable view-only signed link for a board owned by the acting user.')]
#[IsDestructive(false)]
#[ToolClassification(Classification::Content)]
#[ToolEffect(Effect::Write)]
final class ShareBoardLinkTool extends Tool
{
    use AccessesBoards, AdvertisesToolClassification, AdvertisesToolEffect, RespectsEffectCeiling;

    public function handle(Request $request, BoardShareService $shares): Response|ResponseFactory
    {
        $actor = $this->actor(OperatorAbility::McpAdmin);
        $data = $request->validate([
            'id' => ['required', 'string', 'max:36'],
            'expires_in_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ]);
        $board = $this->ownedBoard($actor, $data['id']);

        if ($board === null) {
            return $this->boardRefused();
        }

        $share = $shares->create(
            $actor,
            $board,
            BoardShareKind::Link,
            expiresAt: isset($data['expires_in_minutes'])
                ? now()->addMinutes((int) $data['expires_in_minutes'])
                : null,
        );

        return Response::structured([
            'share_id' => $share->id,
            'url' => $share->signedUrl(),
            'expires_at' => $share->expires_at?->toISOString(),
        ]);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->max(36)->description('Owned board UUIDv7.')->required(),
            'expires_in_minutes' => $schema->integer()->min(1)->max(525600)->description('Optional link lifetime in minutes.'),
        ];
    }
}

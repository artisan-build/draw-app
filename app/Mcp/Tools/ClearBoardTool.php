<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\AccessesBoards;
use App\Mcp\Tools\Concerns\PresentsBoards;
use App\Services\BoardSceneService;
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

#[Name('clear_board')]
#[Description('Remove every element from an editable board while preserving its files and safe app state.')]
#[IsDestructive]
#[ToolClassification(Classification::Content)]
#[ToolEffect(Effect::Destructive)]
final class ClearBoardTool extends Tool
{
    use AccessesBoards, AdvertisesToolClassification, AdvertisesToolEffect, PresentsBoards, RespectsEffectCeiling;

    public function handle(Request $request, BoardSceneService $scenes): Response|ResponseFactory
    {
        $actor = $this->actor(OperatorAbility::McpAdmin);
        $data = $request->validate(['id' => ['required', 'string', 'max:36']]);
        $board = $this->editableBoard($actor, $data['id']);

        return $board === null
            ? $this->boardRefused()
            : Response::structured($this->boardPayload($scenes->clear($actor, $board)));
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->string()->max(36)->description('Editable board UUIDv7.')->required()];
    }
}

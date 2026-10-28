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

#[Name('delete_elements')]
#[Description('Mark bounded element ids deleted without changing other elements.')]
#[IsDestructive]
#[ToolClassification(Classification::Content)]
#[ToolEffect(Effect::Destructive)]
final class DeleteElementsTool extends Tool
{
    use AccessesBoards, AdvertisesToolClassification, AdvertisesToolEffect, PresentsBoards, RespectsEffectCeiling;

    public function handle(Request $request, BoardSceneService $scenes): Response|ResponseFactory
    {
        $actor = $this->actor(OperatorAbility::McpAdmin);
        $data = $request->validate([
            'id' => ['required', 'string', 'max:36'],
            'element_ids' => ['required', 'array', 'min:1', 'max:200'],
            'element_ids.*' => ['string', 'max:255', 'distinct'],
        ]);
        $board = $this->editableBoard($actor, $data['id']);

        return $board === null
            ? $this->boardRefused()
            : Response::structured($this->boardPayload($scenes->deleteElements($actor, $board, $data['element_ids'])));
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->max(36)->description('Editable board UUIDv7.')->required(),
            'element_ids' => $schema->array()->items($schema->string()->max(255))->min(1)->max(200)->unique()->description('Stable element ids.')->required(),
        ];
    }
}

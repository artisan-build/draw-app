<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\McpSceneInput;
use App\Mcp\Tools\Concerns\AccessesBoards;
use App\Mcp\Tools\Concerns\BuildsDrawSchemas;
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

#[Name('add_elements')]
#[Description('Convert and add bounded Excalidraw skeleton elements without replacing existing elements.')]
#[IsDestructive(false)]
#[ToolClassification(Classification::Content)]
#[ToolEffect(Effect::Write)]
final class AddElementsTool extends Tool
{
    use AccessesBoards, AdvertisesToolClassification, AdvertisesToolEffect, BuildsDrawSchemas, PresentsBoards, RespectsEffectCeiling;

    public function handle(Request $request, BoardSceneService $scenes, McpSceneInput $input): Response|ResponseFactory
    {
        $actor = $this->actor(OperatorAbility::McpAdmin);
        $data = $request->validate([
            'id' => ['required', 'string', 'max:36'],
            'elements' => ['required', 'array', 'min:1', 'max:200'],
            'elements.*' => ['array'],
        ]);
        $board = $this->editableBoard($actor, $data['id']);
        $elements = $input->skeletons($request->array('elements'));

        return $board === null
            ? $this->boardRefused()
            : Response::structured($this->boardPayload($scenes->addSkeletons($actor, $board, $elements)));
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->max(36)->description('Editable board UUIDv7.')->required(),
            'elements' => $this->skeletonsSchema($schema),
        ];
    }
}

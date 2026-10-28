<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\AccessesBoards;
use App\Mcp\Tools\Concerns\PresentsBoards;
use App\Models\Board;
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

#[Name('create_board')]
#[Description('Create a private empty Draw board for the acting user.')]
#[IsDestructive(false)]
#[ToolClassification(Classification::Content)]
#[ToolEffect(Effect::Write)]
final class CreateBoardTool extends Tool
{
    use AccessesBoards, AdvertisesToolClassification, AdvertisesToolEffect, PresentsBoards, RespectsEffectCeiling;

    public function handle(Request $request): ResponseFactory
    {
        $actor = $this->actor(OperatorAbility::McpAdmin);
        $data = $request->validate(['title' => ['required', 'string', 'max:120']]);
        $board = Board::query()->create([
            'creator_id' => $actor->id,
            'title' => $data['title'],
        ]);

        return Response::structured($this->boardPayload($board));
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return ['title' => $schema->string()->max(120)->description('Board title.')->required()];
    }
}

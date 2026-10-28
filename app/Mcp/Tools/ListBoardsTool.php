<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\AccessesBoards;
use App\Mcp\Tools\Concerns\PresentsBoards;
use App\Models\Board;
use App\Services\BoardAccess;
use ArtisanBuild\BuiltForCloud\Mcp\AdvertisesToolClassification;
use ArtisanBuild\BuiltForCloud\Mcp\AdvertisesToolEffect;
use ArtisanBuild\BuiltForCloud\Mcp\Classification;
use ArtisanBuild\BuiltForCloud\Mcp\Effect;
use ArtisanBuild\BuiltForCloud\Mcp\RespectsEffectCeiling;
use ArtisanBuild\BuiltForCloud\Mcp\ToolClassification;
use ArtisanBuild\BuiltForCloud\Mcp\ToolEffect;
use ArtisanBuild\BuiltForCloud\OperatorAbility;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_boards')]
#[Description('List boards the acting user owns or can access through a direct or team share.')]
#[IsReadOnly]
#[ToolClassification(Classification::Content)]
#[ToolEffect(Effect::Read)]
final class ListBoardsTool extends Tool
{
    use AccessesBoards, AdvertisesToolClassification, AdvertisesToolEffect, PresentsBoards, RespectsEffectCeiling;

    public function handle(Request $request, BoardAccess $access): ResponseFactory
    {
        $actor = $this->actor(OperatorAbility::McpRead);

        return Response::structured([
            'boards' => $access->viewableBy($actor)
                ->latest('updated_at')
                ->get()
                ->map(fn (Board $board): array => $this->boardPayload($board, false))
                ->all(),
        ]);
    }
}

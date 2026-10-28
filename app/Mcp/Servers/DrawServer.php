<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddElementsTool;
use App\Mcp\Tools\AddMermaidTool;
use App\Mcp\Tools\AddSlideTool;
use App\Mcp\Tools\ClearBoardTool;
use App\Mcp\Tools\CreateBoardTool;
use App\Mcp\Tools\DeleteElementsTool;
use App\Mcp\Tools\GetBoardTool;
use App\Mcp\Tools\ListBoardsTool;
use App\Mcp\Tools\ShareBoardLinkTool;
use App\Mcp\Tools\UpdateElementsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Draw')]
#[Version('1.0.0')]
#[Instructions('Create, inspect, edit, and share Draw whiteboards. Changes persist immediately and signal open viewers to refresh.')]
final class DrawServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ListBoardsTool::class,
        CreateBoardTool::class,
        GetBoardTool::class,
        AddElementsTool::class,
        UpdateElementsTool::class,
        DeleteElementsTool::class,
        ClearBoardTool::class,
        AddMermaidTool::class,
        AddSlideTool::class,
        ShareBoardLinkTool::class,
    ];
}

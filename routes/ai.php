<?php

declare(strict_types=1);

use App\Mcp\Servers\DrawServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', DrawServer::class)
    ->middleware('bfc.mcp:product,read');

Mcp::web('/mcp/write', DrawServer::class)
    ->middleware('bfc.mcp:product,write');

Mcp::web('/mcp/destructive', DrawServer::class)
    ->middleware('bfc.mcp:product,destructive');

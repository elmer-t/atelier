<?php

use App\Mcp\Servers\AtelierAgentServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| Agent MCP surface (ADR-0006)
|--------------------------------------------------------------------------
|
| The agent connects here with a revocable Sanctum personal access token. The
| route is guarded so an unauthenticated (or wrongly-scoped) request is refused;
| each tool additionally enforces the Agent role and the required token ability.
|
*/

Mcp::web('mcp', AtelierAgentServer::class)
    ->middleware('auth:sanctum');

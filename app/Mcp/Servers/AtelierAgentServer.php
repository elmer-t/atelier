<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ArtifactRevisions;
use App\Mcp\Tools\CreateMarkdown;
use App\Mcp\Tools\DeleteMarkdown;
use App\Mcp\Tools\FeedbackDigestTool;
use App\Mcp\Tools\ListArtifacts;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\ProjectThreads;
use App\Mcp\Tools\ReadArtifact;
use App\Mcp\Tools\RenameMarkdown;
use App\Mcp\Tools\ReorderArtifacts;
use App\Mcp\Tools\ReplyToThread;
use App\Mcp\Tools\UpdateMarkdown;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

/**
 * The agent-facing MCP surface (ADR-0006). A dedicated Agent User connects with a
 * revocable Sanctum token and works within a fixed capability boundary: it reads
 * every artifact type and replies to feedback, authors markdown only, and can never
 * mark a Thread Resolved or touch project lifecycle — those tools simply do not exist
 * here, and the ones that do are gated by role and token ability.
 */
class AtelierAgentServer extends Server
{
    protected string $name = 'Atelier Agent';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Read the Creator's projects and artifacts, author markdown artifacts, and reply to
        feedback. Pull the Feedback digest per project to learn where to act. You shape
        content; the human controls exposure — you cannot resolve threads, change project
        settings, or upload HTML/File artifacts.
        MARKDOWN;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        // Reading
        ListProjects::class,
        ListArtifacts::class,
        ReadArtifact::class,
        ArtifactRevisions::class,
        ProjectThreads::class,
        FeedbackDigestTool::class,
        // Authoring markdown
        CreateMarkdown::class,
        UpdateMarkdown::class,
        RenameMarkdown::class,
        DeleteMarkdown::class,
        ReorderArtifacts::class,
        // The feedback loop
        ReplyToThread::class,
    ];
}

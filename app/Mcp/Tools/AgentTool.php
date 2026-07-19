<?php

namespace App\Mcp\Tools;

use App\Enums\ArtifactType;
use App\Models\Artifact;
use App\Models\Project;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base for every tool on the agent surface. Enforces the capability boundary in one
 * place — the acting User must be an Agent (role gate) holding the required token
 * ability — so it holds regardless of the arguments a tool is called with (ADR-0006).
 */
abstract class AgentTool extends Tool
{
    /**
     * The Sanctum token ability this tool requires.
     */
    abstract protected function requiredAbility(): string;

    /**
     * Perform the tool's work now that the Agent and its ability are established.
     */
    abstract protected function perform(Request $request, User $agent): Response;

    public function handle(Request $request): Response
    {
        $agent = $request->user();

        if (! $agent instanceof User || ! $agent->isAgent()) {
            return Response::error('This action is available only to an authenticated Agent user.');
        }

        if (! $agent->tokenCan($this->requiredAbility())) {
            return Response::error('The agent token lacks the required ['.$this->requiredAbility().'] ability.');
        }

        return $this->perform($request, $agent);
    }

    /**
     * Resolve a project account-wide (single-operator: the Creator owns them all).
     */
    protected function project(Request $request): ?Project
    {
        return Project::find((int) $request->get('project'));
    }

    /**
     * Resolve any Artifact by id.
     */
    protected function artifact(Request $request, string $key = 'artifact'): ?Artifact
    {
        return Artifact::find((int) $request->get($key));
    }

    /**
     * Resolve an Artifact that must be markdown for authoring; null when missing or
     * not markdown (HTML/File authoring is never exposed to the agent).
     */
    protected function markdownArtifact(Request $request, string $key = 'artifact'): ?Artifact
    {
        $artifact = $this->artifact($request, $key);

        return $artifact?->type === ArtifactType::Markdown ? $artifact : null;
    }
}

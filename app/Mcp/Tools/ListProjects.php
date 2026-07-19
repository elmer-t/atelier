<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("List the Creator's projects so the agent can find the one to work on.")]
class ListProjects extends AgentTool
{
    protected function requiredAbility(): string
    {
        return AgentAbilities::ARTIFACT_READ;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function perform(Request $request, User $agent): Response
    {
        $projects = Project::query()
            ->withCount('artifacts')
            ->orderBy('id')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'title' => $project->title,
                'visibility' => $project->visibility->value,
                'status' => $project->status->value,
                'artifacts' => $project->artifacts_count,
            ])
            ->all();

        return Response::json(['projects' => $projects]);
    }
}

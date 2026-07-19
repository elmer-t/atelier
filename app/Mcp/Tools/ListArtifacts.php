<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\Artifact;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("List a project's artifacts in their manual order, with type, title and placement.")]
class ListArtifacts extends AgentTool
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
        return [
            'project' => $schema->integer()->description('The project id.')->required(),
        ];
    }

    protected function perform(Request $request, User $agent): Response
    {
        $project = $this->project($request);

        if ($project === null) {
            return Response::error('Project not found.');
        }

        $artifacts = $project->artifacts()->get()->map(fn (Artifact $artifact): array => [
            'id' => $artifact->id,
            'type' => $artifact->type->value,
            'title' => $artifact->title,
            'placement' => $artifact->placement?->value,
            'sort_order' => $artifact->sort_order,
        ])->all();

        return Response::json(['artifacts' => $artifacts]);
    }
}

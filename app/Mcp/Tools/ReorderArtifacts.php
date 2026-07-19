<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Reorder the artifacts within a project by supplying their ids in the desired order.')]
class ReorderArtifacts extends AgentTool
{
    protected function requiredAbility(): string
    {
        return AgentAbilities::ARTIFACT_WRITE;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->integer()->description('The project id.')->required(),
            'order' => $schema->array()->description('Artifact ids in the desired order.')->required(),
        ];
    }

    protected function perform(Request $request, User $agent): Response
    {
        $request->validate([
            'order' => 'required|array|min:1',
            'order.*' => 'integer',
        ]);

        $project = $this->project($request);

        if ($project === null) {
            return Response::error('Project not found.');
        }

        /** @var list<int> $order */
        $order = array_map('intval', (array) $request->get('order'));
        $ownedIds = $project->artifacts()->pluck('id')->all();

        if (array_diff($order, $ownedIds) !== []) {
            return Response::error('The order must reference only artifacts in this project.');
        }

        foreach ($order as $position => $artifactId) {
            $project->artifacts()->whereKey($artifactId)->update(['sort_order' => $position + 1]);
        }

        return Response::json([
            'order' => $project->artifacts()->pluck('id')->all(),
        ]);
    }
}

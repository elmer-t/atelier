<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a markdown artifact that no longer belongs. HTML and File artifacts are never deletable through the agent.')]
class DeleteMarkdown extends AgentTool
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
            'artifact' => $schema->integer()->description('The markdown artifact id.')->required(),
        ];
    }

    protected function perform(Request $request, User $agent): Response
    {
        $artifact = $this->markdownArtifact($request);

        if ($artifact === null) {
            return Response::error('Markdown artifact not found.');
        }

        $id = $artifact->id;
        $artifact->delete();

        return Response::json(['deleted' => true, 'artifact_id' => $id]);
    }
}

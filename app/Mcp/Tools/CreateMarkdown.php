<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\User;
use App\Support\Artifacts\MarkdownRevisionWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a markdown artifact in a project. Its first Revision is attributed to the agent.')]
class CreateMarkdown extends AgentTool
{
    public function __construct(private MarkdownRevisionWriter $writer) {}

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
            'title' => $schema->string()->description('The artifact title.')->required(),
            'body' => $schema->string()->description('The markdown body.')->required(),
        ];
    }

    protected function perform(Request $request, User $agent): Response
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $project = $this->project($request);

        if ($project === null) {
            return Response::error('Project not found.');
        }

        $artifact = $this->writer->create(
            $project,
            (string) $request->get('title'),
            (string) $request->get('body'),
            $agent,
        );

        return Response::json([
            'artifact_id' => $artifact->id,
            'revision_id' => $artifact->current_revision_id,
        ]);
    }
}

<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\User;
use App\Support\Artifacts\MarkdownRevisionWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description("Update a markdown artifact's body in place. Each change appends a Revision attributed to the agent; a byte-identical update is a no-op.")]
class UpdateMarkdown extends AgentTool
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
            'artifact' => $schema->integer()->description('The markdown artifact id.')->required(),
            'body' => $schema->string()->description('The new markdown body.')->required(),
        ];
    }

    protected function perform(Request $request, User $agent): Response
    {
        $request->validate(['body' => 'required|string']);

        $artifact = $this->markdownArtifact($request);

        if ($artifact === null) {
            return Response::error('Markdown artifact not found.');
        }

        $revision = $this->writer->update($artifact, (string) $request->get('body'), $agent);

        return Response::json([
            'artifact_id' => $artifact->id,
            'changed' => $revision !== null,
            'revision_id' => $revision?->id ?? $artifact->current_revision_id,
        ]);
    }
}

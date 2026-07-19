<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Rename a markdown artifact. Renaming is metadata, not content, so it appends no Revision.')]
class RenameMarkdown extends AgentTool
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
            'title' => $schema->string()->description('The new title.')->required(),
        ];
    }

    protected function perform(Request $request, User $agent): Response
    {
        $request->validate(['title' => 'required|string|max:255']);

        $artifact = $this->markdownArtifact($request);

        if ($artifact === null) {
            return Response::error('Markdown artifact not found.');
        }

        $artifact->update(['title' => (string) $request->get('title')]);

        return Response::json(['artifact_id' => $artifact->id, 'title' => $artifact->title]);
    }
}

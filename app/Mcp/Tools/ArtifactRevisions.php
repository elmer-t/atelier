<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\ArtifactRevision;
use App\Models\User;
use App\Support\LineDiffer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("Read a markdown artifact's Revision history with diffs, so the agent can see how it evolved and who authored each change.")]
class ArtifactRevisions extends AgentTool
{
    public function __construct(private LineDiffer $differ) {}

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
            'artifact' => $schema->integer()->description('The markdown artifact id.')->required(),
        ];
    }

    protected function perform(Request $request, User $agent): Response
    {
        $artifact = $this->markdownArtifact($request);

        if ($artifact === null) {
            return Response::error('Markdown artifact not found.');
        }

        $revisions = $artifact->revisions()->with('author')->get()->values();
        $previous = null;

        $history = $revisions->map(function (ArtifactRevision $revision, int $index) use (&$previous): array {
            $entry = [
                'revision' => $index + 1,
                'author' => $revision->author?->name,
                'author_kind' => $revision->author?->isHuman() ? 'human' : 'agent',
                'created_at' => $revision->created_at?->toIso8601String(),
                'diff' => $previous === null ? null : $this->differ->unified($previous, $revision->body),
            ];

            $previous = $revision->body;

            return $entry;
        })->all();

        return Response::json(['revisions' => $history]);
    }
}

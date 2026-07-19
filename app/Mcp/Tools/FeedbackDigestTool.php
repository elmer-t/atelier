<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\User;
use App\Support\Artifacts\FeedbackDigest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('feedback-digest')]
#[Description("Pull a project's Feedback digest in one read: its unresolved Threads plus the markdown artifacts a human edited since the agent's own last Revision, each with its diff.")]
class FeedbackDigestTool extends AgentTool
{
    public function __construct(private FeedbackDigest $digest) {}

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

        return Response::json($this->digest->for($project, $agent));
    }
}

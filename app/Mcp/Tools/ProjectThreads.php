<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("Read the comment Threads on a project's artifacts, so the agent can see the feedback left on them.")]
class ProjectThreads extends AgentTool
{
    protected function requiredAbility(): string
    {
        return AgentAbilities::COMMENT_READ;
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

        $threads = Comment::query()
            ->roots()
            ->whereHas('artifact', fn ($query) => $query->where('project_id', $project->id))
            ->with(['author', 'artifact', 'replies.author'])
            ->orderBy('id')
            ->get()
            ->map(fn (Comment $thread): array => [
                'thread_id' => $thread->id,
                'artifact_id' => $thread->artifact_id,
                'artifact_title' => $thread->artifact->title,
                'author' => $thread->author->name,
                'body' => $thread->body,
                'anchor' => $thread->anchor,
                'resolved' => $thread->isResolved(),
                'replies' => $thread->replies->map(fn (Comment $reply): array => [
                    'author' => $reply->author->name,
                    'body' => $reply->body,
                ])->all(),
            ])
            ->all();

        return Response::json(['threads' => $threads]);
    }
}

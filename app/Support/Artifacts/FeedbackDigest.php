<?php

namespace App\Support\Artifacts;

use App\Enums\ArtifactType;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use App\Support\LineDiffer;

/**
 * The synthesized, per-project view an Agent pulls to learn where it must act
 * (ADR-0006 / CONTEXT.md): the project's unresolved Threads, plus the markdown
 * Artifacts a human has edited since the Agent's own last Revision on them, each
 * with its diff. Computed server-side over data the primitives already expose;
 * read-only and Agent-facing — never surfaced to Clients.
 */
class FeedbackDigest
{
    public function __construct(private LineDiffer $differ) {}

    /**
     * @return array{
     *     project: array{id: int, title: string},
     *     unresolved_threads: list<array<string, mixed>>,
     *     human_edits: list<array<string, mixed>>
     * }
     */
    public function for(Project $project, User $agent): array
    {
        return [
            'project' => ['id' => $project->id, 'title' => $project->title],
            'unresolved_threads' => $this->unresolvedThreads($project),
            'human_edits' => $this->humanEdits($project, $agent),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unresolvedThreads(Project $project): array
    {
        return Comment::query()
            ->roots()
            ->whereNull('resolved_at')
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
                'replies' => $thread->replies->map(fn (Comment $reply): array => [
                    'author' => $reply->author->name,
                    'body' => $reply->body,
                ])->all(),
            ])
            ->all();
    }

    /**
     * Markdown Artifacts a human edited after the Agent's own last Revision, with
     * the diff from that Revision to the current content.
     *
     * @return list<array<string, mixed>>
     */
    private function humanEdits(Project $project, User $agent): array
    {
        $edits = [];

        $artifacts = $project->artifacts()
            ->where('type', ArtifactType::Markdown->value)
            ->with('revisions')
            ->get();

        foreach ($artifacts as $artifact) {
            $agentLast = $artifact->revisions
                ->where('user_id', $agent->id)
                ->last();

            // No agent baseline, or nothing newer than it: no human edit to report.
            if ($agentLast === null || $artifact->current_revision_id === $agentLast->id) {
                continue;
            }

            $edits[] = [
                'artifact_id' => $artifact->id,
                'title' => $artifact->title,
                'since_revision_id' => $agentLast->id,
                'diff' => $this->differ->unified($agentLast->body, (string) $artifact->body),
            ];
        }

        return $edits;
    }
}

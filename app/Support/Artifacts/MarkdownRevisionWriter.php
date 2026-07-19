<?php

namespace App\Support\Artifacts;

use App\Enums\ArtifactType;
use App\Models\Artifact;
use App\Models\ArtifactRevision;
use App\Models\Project;
use App\Models\User;

/**
 * The single append-a-Revision path shared by the admin editor and the agent MCP
 * surface (#15/#17). Creating establishes Revision 1; updating appends a Revision
 * only when the body actually changed and repoints the Artifact at it. History is
 * never rewritten — a restore is just an update with an older body.
 */
class MarkdownRevisionWriter
{
    /**
     * Create a markdown Artifact with its first Revision, attributed to the author.
     */
    public function create(Project $project, string $title, string $body, User $author, ?int $sortOrder = null): Artifact
    {
        $artifact = $project->artifacts()->create([
            'title' => $title,
            'type' => ArtifactType::Markdown,
            'sort_order' => $sortOrder ?? (int) $project->artifacts()->max('sort_order') + 1,
        ]);

        $this->appendRevision($artifact, $body, $author);

        return $artifact;
    }

    /**
     * Append a Revision attributed to the author and repoint current, unless the
     * body is byte-identical to the current Revision (a no-op that adds no history).
     */
    public function update(Artifact $artifact, string $body, User $author): ?ArtifactRevision
    {
        if ($artifact->currentRevision !== null && $artifact->currentRevision->body === $body) {
            return null;
        }

        return $this->appendRevision($artifact, $body, $author);
    }

    /**
     * Restore an older Revision by copying its body forward as a new Revision,
     * deleting nothing. A no-op if that body already matches current.
     */
    public function restore(Artifact $artifact, ArtifactRevision $revision, User $author): ?ArtifactRevision
    {
        return $this->update($artifact, $revision->body, $author);
    }

    private function appendRevision(Artifact $artifact, string $body, User $author): ArtifactRevision
    {
        $revision = $artifact->revisions()->create([
            'body' => $body,
            'user_id' => $author->id,
        ]);

        $artifact->current_revision_id = $revision->id;
        $artifact->save();
        $artifact->setRelation('currentRevision', $revision);

        return $revision;
    }
}

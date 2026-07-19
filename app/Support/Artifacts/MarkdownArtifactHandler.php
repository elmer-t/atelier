<?php

namespace App\Support\Artifacts;

use App\Enums\ArtifactOrigin;
use App\Models\Artifact;

/**
 * Markdown artifacts store their body in the database and render on the app origin,
 * so there are no per-artifact on-disk files to remove. See docs/atelier.specs.md §5.1.
 */
class MarkdownArtifactHandler implements ArtifactHandler
{
    public function origin(): ArtifactOrigin
    {
        return ArtifactOrigin::App;
    }

    public function purge(Artifact $artifact): void
    {
        // Body lives in the DB; markdown images are shared per-project storage.
    }
}

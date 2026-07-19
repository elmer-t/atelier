<?php

namespace App\Support\Artifacts;

use App\Enums\ArtifactOrigin;
use App\Models\Artifact;
use Illuminate\Support\Facades\Storage;

/**
 * File artifacts (images, PDFs, office docs, …) are uploaded files streamed from the
 * app origin and gated by the project session. Purging deletes the stored file.
 */
class FileArtifactHandler implements ArtifactHandler
{
    public function origin(): ArtifactOrigin
    {
        return ArtifactOrigin::App;
    }

    public function purge(Artifact $artifact): void
    {
        if (filled($artifact->stored_path)) {
            Storage::disk('local')->delete($artifact->stored_path);
        }
    }
}

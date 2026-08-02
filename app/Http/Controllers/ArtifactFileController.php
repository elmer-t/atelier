<?php

namespace App\Http\Controllers;

use App\Models\Artifact;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArtifactFileController extends Controller
{
    /**
     * Stream a file artifact inline so the browser renders it in the stage (images,
     * PDFs, office docs, …), falling back to a download for unsupported types.
     */
    public function show(Project $project, Artifact $artifact): StreamedResponse
    {
        return $this->stream($artifact, inline: true);
    }

    /**
     * Stream a file artifact as an attachment (forced download).
     */
    public function download(Project $project, Artifact $artifact): StreamedResponse
    {
        return $this->stream($artifact, inline: false);
    }

    /**
     * Stream the artifact's file through the app so the project's access checks
     * (applied via route middleware) are enforced — files are never linked
     * directly from a public path. See docs/atelier.specs.md §10.
     */
    private function stream(Artifact $artifact, bool $inline): StreamedResponse
    {
        abort_unless($artifact->isFile(), 404);

        $disk = Storage::disk('local');

        abort_unless(filled($artifact->stored_path) && $disk->exists($artifact->stored_path), 404);

        // Never let the browser second-guess the stored type and sniff a document
        // into executable HTML/SVG on the app origin (ADR-0002 / specs §10).
        $noSniff = ['X-Content-Type-Options' => 'nosniff'];

        if ($inline) {
            return $disk->response(
                $artifact->stored_path,
                $artifact->original_filename,
                $noSniff + (filled($artifact->mime_type) ? ['Content-Type' => $artifact->mime_type] : []),
            );
        }

        return $disk->download($artifact->stored_path, $artifact->original_filename, $noSniff);
    }
}

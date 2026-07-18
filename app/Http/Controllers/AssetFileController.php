<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetFileController extends Controller
{
    /**
     * Stream a file asset inline so the browser renders it in the stage (images,
     * PDFs, office docs, …), falling back to a download for unsupported types.
     */
    public function show(Project $project, Asset $asset): StreamedResponse
    {
        return $this->stream($asset, inline: true);
    }

    /**
     * Stream a file asset as an attachment (forced download).
     */
    public function download(Project $project, Asset $asset): StreamedResponse
    {
        return $this->stream($asset, inline: false);
    }

    /**
     * Stream the asset's file through the app so the project's access checks
     * (applied via route middleware) are enforced — files are never linked
     * directly from a public path. See docs/atelier.specs.md §10.
     */
    private function stream(Asset $asset, bool $inline): StreamedResponse
    {
        abort_unless($asset->isFile(), 404);

        $disk = Storage::disk('local');

        abort_unless(filled($asset->stored_path) && $disk->exists($asset->stored_path), 404);

        if ($inline) {
            return $disk->response(
                $asset->stored_path,
                $asset->original_filename,
                filled($asset->mime_type) ? ['Content-Type' => $asset->mime_type] : [],
            );
        }

        return $disk->download($asset->stored_path, $asset->original_filename);
    }
}

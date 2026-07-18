<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarkdownImageController extends Controller
{
    /**
     * Stream a markdown image through the app so the project's access checks
     * (applied via route middleware) are enforced. Images live under the
     * project's own directory; the filename is validated against traversal.
     * See docs/atelier.specs.md §5.2.
     */
    public function __invoke(Project $project, string $filename): StreamedResponse
    {
        abort_if(str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..'), 404);

        $path = "markdown-images/{$project->id}/{$filename}";
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }
}

<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;

/**
 * Deletes a project and all of its on-disk files: unpacked HTML bundles in
 * sandbox storage and uploaded file artifacts in app storage. Each artifact cleans up
 * via its type handler; the per-project sandbox tree is then removed wholesale as
 * a safety net. Database rows (artifacts) are removed by the foreign-key cascade.
 */
class ProjectDeleter
{
    public function delete(Project $project): void
    {
        foreach ($project->artifacts as $artifact) {
            $artifact->type->handler()->purge($artifact);
        }

        // Remove the entire per-project sandbox tree, catching any orphaned bundles.
        $sandboxDir = rtrim((string) config('atelier.sandbox.path'), '/').'/'.$project->sandbox_token;

        if (File::isDirectory($sandboxDir)) {
            File::deleteDirectory($sandboxDir);
        }

        $project->delete();
    }
}

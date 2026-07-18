<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;

/**
 * Deletes a project and all of its on-disk artifacts: unpacked HTML bundles in
 * sandbox storage and uploaded file assets in app storage. Each asset cleans up
 * via its type handler; the per-project sandbox tree is then removed wholesale as
 * a safety net. Database rows (assets) are removed by the foreign-key cascade.
 */
class ProjectDeleter
{
    public function delete(Project $project): void
    {
        foreach ($project->assets as $asset) {
            $asset->type->handler()->purge($asset);
        }

        // Remove the entire per-project sandbox tree, catching any orphaned bundles.
        $sandboxDir = rtrim((string) config('atelier.sandbox.path'), '/').'/'.$project->sandbox_token;

        if (File::isDirectory($sandboxDir)) {
            File::deleteDirectory($sandboxDir);
        }

        $project->delete();
    }
}

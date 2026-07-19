<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;

/**
 * Revokes a project's current shareable link and issues a fresh one, without
 * archiving the project. Both the slug (public `p/{slug}` routes) and the sandbox
 * token (HTML bundle origin, §6) are rotated, the on-disk bundles are relocated
 * under the new token, and each HTML artifact's `bundle_path` is re-pointed. The
 * session version is also bumped so any live viewer sessions are invalidated.
 *
 * After a reissue the old project view, gate, file and sandbox URLs all stop
 * resolving, while the project itself stays active. See issue #24, §6, §9.
 */
class LinkReissuer
{
    public function reissue(Project $project): void
    {
        $previousToken = $project->sandbox_token;

        $project->slug = Project::generateToken(config('atelier.slug_bytes'));
        $project->sandbox_token = Project::generateToken(config('atelier.sandbox_token_bytes'));
        $project->session_version++;

        $this->relocateBundles($project, $previousToken);

        $project->save();
    }

    /**
     * Move the project's unpacked bundles from the old token directory to the new
     * one and re-point each artifact's stored bundle path.
     */
    private function relocateBundles(Project $project, string $previousToken): void
    {
        $root = rtrim((string) config('atelier.sandbox.path'), '/');
        $from = $root.'/'.$previousToken;
        $to = $root.'/'.$project->sandbox_token;

        if (File::isDirectory($from)) {
            File::ensureDirectoryExists(dirname($to));
            File::moveDirectory($from, $to);
        }

        foreach ($project->artifacts()->whereNotNull('bundle_path')->get() as $artifact) {
            $artifact->rebaseBundlePath($previousToken, $project->sandbox_token);
        }
    }
}

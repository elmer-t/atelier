<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Facades\Session;

/**
 * Owns the per-project password-gate session state.
 *
 * A passed gate is remembered under a session key that embeds the project's
 * `session_version`, so rotating the password (which bumps the version)
 * invalidates every existing viewer session for that project.
 * See docs/atelier.specs.md §4.3.
 */
class ProjectGate
{
    public static function isUnlocked(Project $project): bool
    {
        if ($project->isPublic()) {
            return true;
        }

        return Session::get($project->sessionKey()) === true;
    }

    public static function unlock(Project $project): void
    {
        Session::put($project->sessionKey(), true);
    }
}

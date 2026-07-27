<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Support\ProjectGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the public project routes:
 *
 *  - Archived or expired projects are hard-disabled (404) even via direct link.
 *  - Private projects require a passed password gate for the current session;
 *    otherwise the viewer is redirected to the gate.
 *  - Public (and unlocked private) projects pass through.
 *  - A signed-in Creator skips the gate. They set the password and can read every
 *    project from the admin area anyway, so the gate would only stand between them
 *    and their own content — notably when following a comment link out of the Users
 *    panel (#30). It is the same bypass `admin.projects.artifact-preview` already
 *    makes for cover thumbnails. Hard-disabled projects still 404 for everyone.
 *
 * See docs/atelier.specs.md §4, §9.
 */
class EnsureProjectAccessible
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        abort_unless($project instanceof Project, 404);

        if ($project->isHardDisabled()) {
            abort(404);
        }

        if ($project->isPrivate() && ! ProjectGate::isUnlocked($project) && ! $request->user()?->isCreator()) {
            return redirect()->route('project.gate', $project);
        }

        return $next($request);
    }
}

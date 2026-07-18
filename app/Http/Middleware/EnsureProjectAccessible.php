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
 *  - Archived projects are hard-disabled (404) even via direct link.
 *  - Private projects require a passed password gate for the current session;
 *    otherwise the viewer is redirected to the gate.
 *  - Public (and unlocked private) projects pass through.
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

        if ($project->isArchived()) {
            abort(404);
        }

        if ($project->isPrivate() && ! ProjectGate::isUnlocked($project)) {
            return redirect()->route('project.gate', $project);
        }

        return $next($request);
    }
}

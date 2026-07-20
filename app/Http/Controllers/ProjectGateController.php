<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectGateController extends Controller
{
    /**
     * Show the password prompt for a private project. Archived or expired
     * projects are hard-disabled (404); public or already-unlocked projects skip
     * straight to the view.
     */
    public function show(Project $project): View|RedirectResponse
    {
        abort_if($project->isHardDisabled(), 404);

        if ($project->isPublic() || ProjectGate::isUnlocked($project)) {
            return redirect()->route('project.show', $project);
        }

        return view('public.gate', ['project' => $project]);
    }

    /**
     * Verify the submitted password and, on success, establish the per-project
     * session and redirect to the project view.
     */
    public function unlock(Request $request, Project $project): RedirectResponse
    {
        abort_if($project->isHardDisabled(), 404);

        if ($project->isPublic()) {
            return redirect()->route('project.show', $project);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! $project->checkPassword($validated['password'])) {
            throw ValidationException::withMessages([
                'password' => __('The password is incorrect.'),
            ]);
        }

        ProjectGate::unlock($project);

        return redirect()->route('project.show', $project);
    }
}

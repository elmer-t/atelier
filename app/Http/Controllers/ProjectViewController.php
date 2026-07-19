<?php

namespace App\Http\Controllers;

use App\Models\Artifact;
use App\Models\Project;
use App\Services\MarkdownRenderer;
use Illuminate\View\View;

class ProjectViewController extends Controller
{
    /**
     * Render the project "stage": a sidebar of ordered stage artifacts plus the
     * selected artifact in the main area, and a separate downloads list. When no
     * artifact is given, the first stage artifact (by sort order) is shown.
     * See docs/atelier.specs.md §7.1.
     */
    public function show(Project $project, MarkdownRenderer $markdown, ?Artifact $artifact = null): View
    {
        $project->load('artifacts.currentRevision');

        // Download-only files never open in the stage.
        abort_if($artifact !== null && ! $artifact->showsInStage(), 404);

        $current = $artifact ?? $project->artifacts->first(fn (Artifact $a) => $a->showsInStage());

        $renderedBody = $current?->isMarkdown()
            ? $markdown->render($current->body)
            : null;

        return view('public.show', [
            'project' => $project,
            'current' => $current,
            'renderedBody' => $renderedBody,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Project;
use App\Services\MarkdownRenderer;
use Illuminate\View\View;

class ProjectViewController extends Controller
{
    /**
     * Render the project "stage": a sidebar of ordered stage assets plus the
     * selected asset in the main area, and a separate downloads list. When no
     * asset is given, the first stage asset (by sort order) is shown.
     * See docs/atelier.specs.md §7.1.
     */
    public function show(Project $project, MarkdownRenderer $markdown, ?Asset $asset = null): View
    {
        $project->load('assets');

        // Download-only files never open in the stage.
        abort_if($asset !== null && ! $asset->showsInStage(), 404);

        $current = $asset ?? $project->assets->first(fn (Asset $a) => $a->showsInStage());

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

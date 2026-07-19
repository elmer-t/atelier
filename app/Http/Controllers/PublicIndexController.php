<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Models\Project;
use Illuminate\View\View;

class PublicIndexController extends Controller
{
    /**
     * The public index lists all active, public projects. Private and archived
     * projects never appear here. See docs/atelier.specs.md §7.3.
     */
    public function __invoke(): View
    {
        $projects = Project::query()
            ->where('status', ProjectStatus::Active)
            ->where('visibility', ProjectVisibility::Public)
            ->withCount('artifacts')
            ->latest()
            ->get();

        return view('public.index', ['projects' => $projects]);
    }
}

<?php

use App\Http\Controllers\ArtifactFileController;
use App\Livewire\Admin\Projects\Index as ProjectsIndex;
use App\Livewire\Admin\Projects\Manage as ProjectsManage;
use App\Livewire\Admin\Users\AgentAccess;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Admin\Users\Show as UsersShow;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'creator'])->prefix('admin')->group(function () {
    Route::redirect('/', 'admin/projects');

    Route::livewire('projects', ProjectsIndex::class)->name('admin.projects');
    Route::livewire('projects/{project}', ProjectsManage::class)->name('admin.projects.manage');

    // Creator-side preview of a file artifact, used for the cover thumbnail on
    // the manage screen. The public equivalent sits behind `project.accessible`,
    // which the Creator has no unlocked session for on a private project.
    Route::get('projects/{project}/artifacts/{artifact}/preview', [ArtifactFileController::class, 'show'])
        ->scopeBindings()
        ->name('admin.projects.artifact-preview');

    Route::livewire('users', UsersIndex::class)->name('admin.users');

    // Declared before the `{user}` binding so the agent panel is reachable before
    // an Agent User has ever been provisioned.
    Route::livewire('users/agent', AgentAccess::class)->name('admin.users.agent');
    Route::livewire('users/{user}', UsersShow::class)->name('admin.users.show');
});

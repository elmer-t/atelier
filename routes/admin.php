<?php

use App\Livewire\Admin\Projects\Index as ProjectsIndex;
use App\Livewire\Admin\Projects\Manage as ProjectsManage;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'creator'])->prefix('admin')->group(function () {
    Route::redirect('/', 'admin/projects');

    Route::livewire('projects', ProjectsIndex::class)->name('admin.projects');
    Route::livewire('projects/{project}', ProjectsManage::class)->name('admin.projects.manage');
});

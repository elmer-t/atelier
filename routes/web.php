<?php

use App\Http\Controllers\ArtifactFileController;
use App\Http\Controllers\MarkdownImageController;
use App\Http\Controllers\ProjectGateController;
use App\Http\Controllers\ProjectViewController;
use App\Http\Controllers\PublicIndexController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (link recipients — never log in)
|--------------------------------------------------------------------------
*/

Route::get('/', PublicIndexController::class)->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::prefix('p/{project:slug}')->group(function () {
    // Password gate — reachable without an established session (but not for archived projects).
    Route::get('unlock', [ProjectGateController::class, 'show'])->name('project.gate');
    Route::post('unlock', [ProjectGateController::class, 'unlock'])
        ->middleware('throttle:10,1')
        ->name('project.unlock');

    // Gated content: archived => 404, private => requires session.
    Route::middleware('project.accessible')->group(function () {
        Route::get('/', [ProjectViewController::class, 'show'])->name('project.show');
        Route::get('a/{artifact}', [ProjectViewController::class, 'show'])
            ->scopeBindings()
            ->name('project.artifact');
        Route::get('a/{artifact}/file', [ArtifactFileController::class, 'show'])
            ->scopeBindings()
            ->name('project.artifact.file');
        Route::get('a/{artifact}/download', [ArtifactFileController::class, 'download'])
            ->scopeBindings()
            ->name('project.artifact.download');
        Route::get('images/{filename}', MarkdownImageController::class)->name('project.image');
    });
});

/*
|--------------------------------------------------------------------------
| Admin (Laravel auth)
|--------------------------------------------------------------------------
*/

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';

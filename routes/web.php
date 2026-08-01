<?php

use App\Http\Controllers\ArtifactFileController;
use App\Http\Controllers\MarkdownImageController;
use App\Http\Controllers\ProjectGateController;
use App\Http\Controllers\ProjectViewController;
use App\Http\Controllers\PublicIndexController;
use App\Http\Controllers\PushSubscriptionController;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (link recipients — never log in)
|--------------------------------------------------------------------------
*/

Route::get('/', PublicIndexController::class)->name('home');

Route::view('privacy', 'privacy')->name('privacy');

/*
|--------------------------------------------------------------------------
| Web push subscriptions (#35)
|--------------------------------------------------------------------------
| Two doors onto the same store: authenticated Creators, and the cookie-recognised
| passwordless commenter (ADR-0003) for Clients. Consent is the browser permission
| grant, so these bypass the email-verification gate deliberately.
*/
Route::middleware('auth')->group(function () {
    Route::post('push/subscribe', [PushSubscriptionController::class, 'subscribe'])->name('push.subscribe');
    Route::post('push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])->name('push.unsubscribe');
});

Route::post('push/client/subscribe', [PushSubscriptionController::class, 'subscribeAsCommenter'])->name('push.client.subscribe');
Route::post('push/client/unsubscribe', [PushSubscriptionController::class, 'unsubscribeAsCommenter'])->name('push.client.unsubscribe');

Route::livewire('dashboard', Dashboard::class)
    ->middleware(['auth', 'verified', 'creator'])
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

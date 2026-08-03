<?php

use App\Enums\UserRole;
use App\Models\Artifact;
use App\Models\Project;
use App\Models\User;

/**
 * The unsaved-work guard is client-side by necessity — `wire:model` on the body is
 * deferred, so at the moment of the click the server still holds the last saved
 * text and cannot tell that anything is unsaved. None of it is reachable from a
 * Livewire test, so it is pinned here.
 *
 * Two habits this file keeps:
 *
 * - Playwright dismisses a `window.confirm` unless told otherwise, which is the
 *   "keep my changes" answer. A test wanting the other answer says so first.
 * - `assertValue()` does not wait for its field to exist; asked for one that has
 *   not arrived yet it blocks rather than failing. So every click that swaps the
 *   form is followed by an `assertSee()` on something in the new panel, which does
 *   wait, before any value is read.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Creator]));

    $this->project = Project::factory()->create(['title' => 'Harbor District']);
    $this->brief = Artifact::factory()->for($this->project)->markdown('# The brief')->create([
        'title' => 'Design Brief',
        'sort_order' => 1,
    ]);
    $this->notes = Artifact::factory()->for($this->project)->markdown('# The notes')->create([
        'title' => 'Meeting Notes',
        'sort_order' => 2,
    ]);

    // Relative: an absolute URL is passed through untouched and would reach the
    // real Herd site rather than the test server and its in-memory database.
    $this->manageUrl = route('admin.projects.manage', $this->project, absolute: false);
});

/**
 * Decide in advance how the Creator answers the discard prompt. The override runs
 * inside an IIFE returning nothing: `script()` hands its result back over the
 * Playwright wire, and a bare assignment would try to serialise the function.
 */
function answerDiscardWith(object $page, bool $discard): void
{
    $page->script(sprintf('(() => { window.confirm = () => %s })()', $discard ? 'true' : 'false'));
}

it('keeps the draft when the Creator declines to discard it', function () {
    $page = visit($this->manageUrl);

    $page->click('@open-artifact-'.$this->brief->id)
        ->assertSee('Insert image')
        ->assertValue('body', '# The brief')
        ->type('body', '# The brief, half rewritten')
        // Playwright dismisses the confirm, which is "keep my changes".
        ->click('@open-artifact-'.$this->notes->id)
        ->assertValue('artifactTitle', 'Design Brief')
        ->assertValue('body', '# The brief, half rewritten')
        ->assertNoJavaScriptErrors();
});

it('switches artifact when the Creator agrees to discard the draft', function () {
    $page = visit($this->manageUrl);

    $page->click('@open-artifact-'.$this->brief->id)
        ->assertSee('Insert image')
        ->type('body', '# Thrown away');

    answerDiscardWith($page, true);

    $page->click('@open-artifact-'.$this->notes->id)
        ->assertValue('artifactTitle', 'Meeting Notes')
        ->assertValue('body', '# The notes')
        ->assertNoJavaScriptErrors();
});

it('stops asking once the draft has been saved', function () {
    $page = visit($this->manageUrl);

    $page->click('@open-artifact-'.$this->brief->id)
        ->assertSee('Insert image')
        ->type('body', '# Saved and safe')
        ->click('Save')
        ->assertSee('Markdown page saved.')
        // Nothing is unsaved now, so the confirm never fires and the click lands.
        ->click('@open-artifact-'.$this->notes->id)
        ->assertValue('artifactTitle', 'Meeting Notes')
        ->assertNoJavaScriptErrors();

    expect($this->brief->refresh()->body)->toBe('# Saved and safe');
});

it('guards Close as well as the artifact list', function () {
    $page = visit($this->manageUrl);

    $page->click('@open-artifact-'.$this->brief->id)
        ->assertSee('Insert image')
        ->type('body', '# Not ready to lose this')
        ->click('@close-artifact')
        // Declined, so the form is still open on the same draft.
        ->assertValue('body', '# Not ready to lose this');

    answerDiscardWith($page, true);

    $page->click('@close-artifact')
        ->assertDontSee('Insert image')
        ->assertNoJavaScriptErrors();
});

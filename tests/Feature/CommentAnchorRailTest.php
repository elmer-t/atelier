<?php

use App\Livewire\Public\ArtifactComments;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use Livewire\Livewire;

/**
 * The feedback rail renders each Thread with the data attributes the client-side
 * anchor pass needs to tie it back to a spot in the stage (ADR-0004).
 */
it('marks the stage as the region anchors resolve against', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Brief')->create();

    $this->get(route('project.show', $project))
        ->assertOk()
        ->assertSee('data-artifact-stage', escape: false);
});

it('renders a text_range Thread with its quote and anchor wiring', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown("# Brief\n\nThe timeline looks tight.")->create();

    $thread = Comment::factory()->for($artifact)->create([
        'body' => 'Can we push this out a week?',
        'anchor' => ['type' => 'text_range', 'quote' => 'The timeline looks tight.'],
    ]);

    $this->get(route('project.artifact', [$project, $artifact]))
        ->assertOk()
        ->assertSee('data-comment-id="'.$thread->id.'"', escape: false)
        ->assertSee('data-anchor-quote="The timeline looks tight."', escape: false)
        // The quote is shown on the card, so the Thread reads as feedback on something.
        ->assertSee('The timeline looks tight.')
        ->assertSee('Can we push this out a week?');
});

it('gives replies no anchor of their own', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    $root = Comment::factory()->for($artifact)->create([
        'anchor' => ['type' => 'text_range', 'quote' => 'Brief'],
    ]);
    $reply = Comment::factory()->replyTo($root)->create(['body' => 'Agreed.']);

    $response = $this->get(route('project.artifact', [$project, $artifact]))->assertOk();

    $response->assertSee('Agreed.');
    $response->assertDontSee('data-comment-id="'.$reply->id.'"', escape: false);
});

it('carries the resolved state onto the anchor so a settled Thread reads as muted', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    Comment::factory()->for($artifact)->resolved()->create([
        'anchor' => ['type' => 'text_range', 'quote' => 'Brief'],
    ]);

    $this->get(route('project.artifact', [$project, $artifact]))
        ->assertOk()
        ->assertSee('data-anchor-resolved="yes"', escape: false);
});

/**
 * Threads are placed level with the text they are about, so the placement pass has to be
 * able to find them, and each one has to say where it wants to sit.
 */
it('marks each Thread as a placeable item so the rail can align it with its text', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown("# Brief\n\nThe timeline looks tight.")->create();

    $thread = Comment::factory()->for($artifact)->create([
        'anchor' => ['type' => 'text_range', 'quote' => 'The timeline looks tight.'],
    ]);

    $this->get(route('project.artifact', [$project, $artifact]))
        ->assertOk()
        ->assertSee('data-rail-item', escape: false)
        ->assertSee('data-comment-id="'.$thread->id.'"', escape: false);
});

/**
 * An artifact with no prose to quote anchors its feedback to a point instead. That depth is
 * the only thing the placement pass and the minimap have to go on, so it has to be rendered.
 */
it('exposes a point anchor’s depth so it can be placed without a quote', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->html()->create();

    Comment::factory()->for($artifact)->create([
        'anchor' => ['type' => 'html_point', 'x' => 40.5, 'y' => 62.5],
    ]);

    $this->get(route('project.artifact', [$project, $artifact]))
        ->assertOk()
        ->assertSee('data-anchor-y="62.5"', escape: false);
});

/**
 * The minimap: one tick per Thread, so the distribution of feedback down the whole
 * document is readable at a glance, however far the rail is scrolled.
 */
it('gives every Thread a minimap tick carrying its identity and settled state', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown("# Brief\n\nThe timeline looks tight.")->create();

    $open = Comment::factory()->for($artifact)->create([
        'anchor' => ['type' => 'text_range', 'quote' => 'Brief'],
    ]);
    $settled = Comment::factory()->for($artifact)->resolved()->create([
        'anchor' => ['type' => 'text_range', 'quote' => 'The timeline looks tight.'],
    ]);

    $response = $this->get(route('project.artifact', [$project, $artifact]))->assertOk();

    $response->assertSee('wire:key="tick-'.$open->id.'"', escape: false);
    $response->assertSee('wire:key="tick-'.$settled->id.'"', escape: false);
});

it('gives replies no minimap tick of their own', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    $root = Comment::factory()->for($artifact)->create([
        'anchor' => ['type' => 'text_range', 'quote' => 'Brief'],
    ]);
    $reply = Comment::factory()->replyTo($root)->create();

    $this->get(route('project.artifact', [$project, $artifact]))
        ->assertOk()
        ->assertSee('wire:key="tick-'.$root->id.'"', escape: false)
        ->assertDontSee('wire:key="tick-'.$reply->id.'"', escape: false);
});

/**
 * Nothing enters the rail without a place in the document: the composer appears only once a
 * spot has been picked, which is what keeps the rail free of a permanently parked panel.
 */
it('withholds the composer until an anchor has been picked', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    $this->get(route('project.artifact', [$project, $artifact]))
        ->assertOk()
        ->assertDontSee('rail-item rail-compose', escape: false)
        ->assertSee('Hover a paragraph and click the pin');
});

/**
 * The empty state and the composer occupy the same placement layer, so both on screen at
 * once means one printed over the other. The prompt has done its job by the time a spot is
 * picked, so it stands down — on a point-anchored artifact as much as a markdown one.
 */
it('drops the empty-state prompt once the composer is open', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->file()->create();

    Livewire::test(ArtifactComments::class, ['artifact' => $artifact])
        ->assertSee('No feedback yet.')
        ->set('draftAnchor', ['type' => 'image_region', 'x' => 50, 'y' => 50])
        ->assertDontSee('No feedback yet.')
        ->assertSee('Add your details to comment');
});

/**
 * A point anchor draws no marker in the stage, so its coordinates are a number with nothing
 * to refer to. The placement still uses the depth; the reader is never shown it.
 */
it('keeps a point anchor’s coordinates out of the rail while still placing by them', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->file()->create();

    Comment::factory()->for($artifact)->create([
        'body' => 'The crop is too tight here.',
        'anchor' => ['type' => 'image_region', 'x' => 50, 'y' => 62.5],
    ]);

    Livewire::test(ArtifactComments::class, ['artifact' => $artifact])
        ->assertSee('The crop is too tight here.')
        ->assertDontSee('Pinned at')
        ->assertSeeHtml('data-anchor-y="62.5"');
});

/**
 * Feedback you have to click open is feedback nobody reads, so a Thread arrives expanded —
 * whole body, replies and all — and folding one down is a per-Thread choice made after the
 * fact. Nothing is collapsed when the rail draws.
 */
it('draws every Thread expanded, with folding reserved for a deliberate choice', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    $thread = Comment::factory()->for($artifact)->create([
        'body' => 'The second half needs a rewrite.',
        'anchor' => ['type' => 'text_range', 'quote' => 'Brief'],
    ]);
    Comment::factory()->replyTo($thread)->create(['body' => 'Agreed, I will take a pass.']);

    $this->get(route('project.artifact', [$project, $artifact]))
        ->assertOk()
        ->assertSee('folded: []', escape: false)
        ->assertSee('data-rail-open="yes"', escape: false)
        ->assertSee("folded.includes({$thread->id}) ? 'no' : 'yes'", escape: false)
        // The replies are in the document from the start, not disclosed on a click.
        ->assertSee('Agreed, I will take a pass.');
});

/**
 * Turning to a Thread holds it level with its text and pushes its neighbours out of the way.
 * The ones above it have to be free to leave the rail: floored at the top edge instead, every
 * Thread the pinned one took the room from would land on the same spot and print over the
 * others — the pile the rail is meant to be incapable of.
 */
it('lets Threads the attended one displaced leave the top of the rail rather than pile on it', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    Comment::factory()->for($artifact)->create([
        'anchor' => ['type' => 'text_range', 'quote' => 'Brief'],
    ]);

    $response = $this->get(route('project.artifact', [$project, $artifact]))->assertOk();

    // Upward from the pin an item clears its lower neighbour and nothing else.
    $response->assertSee('live[i].y = Math.min(live[i].target, live[i + 1].y - live[i].height - gap);', escape: false);
    $response->assertDontSee('Math.max(reserve, Math.min(live[i].target', escape: false);

    // And what left that way is counted at the edge, so it stays one click away.
    $response->assertSee('const risen = live.filter((item) => item.y + item.height + gap < reserve);', escape: false);
    $response->assertSee('this.above = above.length;', escape: false);
});

/**
 * The rail's whole behaviour lives in one `x-data` attribute, delimited by double quotes. A
 * bare `"` anywhere in that script — in a comment as easily as in a string — closes the
 * attribute early, and the remainder of the component spills onto the page as text. Nothing
 * about the source looks wrong when it happens, so the boundary is asserted here instead.
 */
it('keeps the rail’s script inside its attribute rather than spilling it onto the page', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    Comment::factory()->for($artifact)->create([
        'anchor' => ['type' => 'text_range', 'quote' => 'Brief'],
    ]);

    $html = $this->get(route('project.artifact', [$project, $artifact]))->assertOk()->getContent();

    expect($html)->toMatch('/x-data="\{[^"]*bindAnchor\(el, id\)[^"]*\}"/s');
});

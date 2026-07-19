<?php

use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;

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

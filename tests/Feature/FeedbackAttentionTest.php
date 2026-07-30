<?php

use App\Enums\AttentionLevel;
use App\Enums\UserRole;
use App\Livewire\Public\ArtifactComments;
use App\Livewire\Public\ProjectPages;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\CommentRead;
use App\Models\Project;
use App\Models\User;
use App\Support\Artifacts\FeedbackAttention;
use Livewire\Livewire;

beforeEach(function () {
    $this->project = Project::factory()->public()->create();
    $this->artifact = Artifact::factory()->for($this->project)->markdown('# Brief')->create(['title' => 'Brief']);

    $this->client = User::factory()->client()->create();
    $this->creator = User::factory()->create(['role' => UserRole::Creator]);
    $this->stranger = User::factory()->client()->create();
});

/** The level the pages panel would draw for this viewer on that artifact. */
function levelFor(?User $viewer, Artifact $artifact): AttentionLevel
{
    $artifact->project->load('artifacts');

    return app(FeedbackAttention::class)
        ->for($artifact->project, $viewer)[$artifact->id]
        ->level();
}

it('says nothing about an artifact with no threads', function () {
    expect(levelFor($this->client, $this->artifact))->toBe(AttentionLevel::None);
});

it('treats a thread the viewer is not in as open rather than theirs', function () {
    Comment::factory()->for($this->artifact)->for($this->stranger, 'author')->create();

    expect(levelFor($this->client, $this->artifact))->toBe(AttentionLevel::Open);
});

it('leaves a thread the viewer spoke in last as open, not waiting on them', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->stranger, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->client, 'author')->create();

    expect(levelFor($this->client, $this->artifact))->toBe(AttentionLevel::Open);
});

it('raises an unread reply to the viewer own thread', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    expect(levelFor($this->client, $this->artifact))->toBe(AttentionLevel::Unread);
});

it('softens to awaiting once read, and back to unread on a later reply', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    CommentRead::record($this->client, $thread->fresh());

    expect(levelFor($this->client, $this->artifact))->toBe(AttentionLevel::Awaiting);

    // The mark is a watermark, not a boolean: later activity re-raises it unaided.
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    expect(levelFor($this->client, $this->artifact))->toBe(AttentionLevel::Unread);
});

it('holds the creator answerable for every unresolved thread, not only their own', function () {
    Comment::factory()->for($this->artifact)->for($this->stranger, 'author')->create();

    expect(levelFor($this->creator, $this->artifact))->toBe(AttentionLevel::Unread)
        ->and(levelFor($this->client, $this->artifact))->toBe(AttentionLevel::Open);
});

it('settles an artifact whose threads are all resolved', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();
    $thread->forceFill(['resolved_at' => now(), 'resolved_by' => $this->creator->id])->save();

    expect(levelFor($this->client, $this->artifact))->toBe(AttentionLevel::Resolved)
        ->and(levelFor($this->creator, $this->artifact))->toBe(AttentionLevel::Resolved);
});

it('outranks resolved bulk with a single thread that wants the viewer', function () {
    Comment::factory()->count(3)->for($this->artifact)->for($this->stranger, 'author')
        ->create(['resolved_at' => now(), 'resolved_by' => $this->creator->id]);

    $mine = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($mine)->for($this->creator, 'author')->create();

    expect(levelFor($this->client, $this->artifact))->toBe(AttentionLevel::Unread);
});

it('is answerable for nothing when the visitor has no established identity', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    expect(levelFor(null, $this->artifact))->toBe(AttentionLevel::Open);
});

it('records a read when the rail opens a thread, and only when it has moved on', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    $component = Livewire::actingAs($this->client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact]);

    $component->call('markThreadSeen', $thread->id)->assertDispatched('thread-seen');

    expect(CommentRead::count())->toBe(1);

    // Re-opening an unchanged Thread appends nothing and announces nothing.
    $component->call('markThreadSeen', $thread->id)->assertNotDispatched('thread-seen');

    expect(CommentRead::count())->toBe(1);

    // New activity moves the mark, so the trail gains a row rather than losing one.
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    $component->call('markThreadSeen', $thread->id)->assertDispatched('thread-seen');

    expect(CommentRead::count())->toBe(2);
});

it('records nothing for a visitor with no established identity', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();

    Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->call('markThreadSeen', $thread->id)
        ->assertNotDispatched('thread-seen');

    expect(CommentRead::count())->toBe(0);
});

it('records nothing for a thread on another artifact', function () {
    $other = Artifact::factory()->for($this->project)->markdown('# Other')->create();
    $thread = Comment::factory()->for($other)->for($this->client, 'author')->create();

    Livewire::actingAs($this->client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->call('markThreadSeen', $thread->id)
        ->assertNotDispatched('thread-seen');

    expect(CommentRead::count())->toBe(0);
});

it('draws the mark and the words together for each level', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    $panel = Livewire::actingAs($this->client)
        ->test(ProjectPages::class, ['project' => $this->project, 'current' => $this->artifact]);

    $panel->assertSee('data-attention="unread"', false)
        ->assertSee('1 new reply for you');

    CommentRead::record($this->client, $thread->fresh());

    $panel->dispatch('thread-seen')
        ->assertSee('data-attention="awaiting"', false)
        ->assertSee('1 reply still unanswered');
});

it('recognises a returning client by their cookie, not only a logged-in user', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    Livewire::withCookies(['atelier_commenter' => (string) $this->client->id])
        ->test(ProjectPages::class, ['project' => $this->project, 'current' => $this->artifact])
        ->assertSee('data-attention="unread"', false);
});

it('carries the mark through to the rendered project page', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    $this->actingAs($this->client)
        ->get(route('project.show', $this->project))
        ->assertOk()
        ->assertSee('data-attention="unread"', false)
        ->assertSee('stage-attention', false);
});

it('shows an unidentified visitor no personal marks on the rendered page', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    $this->get(route('project.show', $this->project))
        ->assertOk()
        ->assertSee('data-attention="open"', false)
        ->assertDontSee('data-attention="unread"', false);
});

it('marks in the rail exactly the threads the panel counted as new', function () {
    $mine = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($mine)->for($this->creator, 'author')->create();

    // Someone else's conversation is not the client's to catch up on.
    $theirs = Comment::factory()->for($this->artifact)->for($this->stranger, 'author')->create();

    $rail = Livewire::actingAs($this->client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact]);

    expect($rail->instance()->unreadThreadIds)->toBe([$mine->id]);

    // Their Thread is in the rail all the same; it is simply not new to this reader.
    $rail->assertSeeHtml('rail-unread-count')
        ->assertSeeHtml('data-comment-id="'.$theirs->id.'"')
        ->assertSeeHtml('data-rail-unread="yes"');
});

it('retires the mark once the thread has been read, and raises it again on a later reply', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    CommentRead::record($this->client, $thread->fresh());

    $read = Livewire::actingAs($this->client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact]);

    expect($read->instance()->unreadThreadIds)->toBe([]);
    $read->assertDontSeeHtml('data-rail-unread="yes"');

    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    $moved = Livewire::actingAs($this->client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact]);

    expect($moved->instance()->unreadThreadIds)->toBe([$thread->id]);
    $moved->assertSeeHtml('data-rail-unread="yes"');
});

it('leaves the rail unmarked for a visitor with no established identity', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    $rail = Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact]);

    expect($rail->instance()->unreadThreadIds)->toBe([]);

    $rail->assertDontSeeHtml('data-rail-unread="yes"')
        ->assertDontSeeHtml('rail-unread-count');
});

it('drops a reader read history when the reader is erased', function () {
    $thread = Comment::factory()->for($this->artifact)->for($this->client, 'author')->create();
    Comment::factory()->replyTo($thread)->for($this->creator, 'author')->create();

    CommentRead::record($this->client, $thread->fresh());

    $this->client->delete();

    expect(CommentRead::count())->toBe(0);
});

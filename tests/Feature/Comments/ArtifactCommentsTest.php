<?php

use App\Enums\UserRole;
use App\Livewire\Public\ArtifactComments;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ArtifactCommentPosted;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->project = Project::factory()->public()->create();
    $this->artifact = Artifact::factory()->for($this->project)->markdown('# Brief')->create(['title' => 'Brief']);

    // These tests submit instantly, which the submit-timing floor exists to reject.
    // They are about what commenting does, not about who is allowed to reach it;
    // the abuse controls have their own coverage in CommentAbuseControlsTest.
    config(['atelier.comments.min_seconds_before_submit' => 0]);
});

function identify($component, string $name = 'Jane Doe', string $email = 'jane@example.com')
{
    return $component
        ->set('captureName', $name)
        ->set('captureEmail', $email)
        ->call('saveIdentity')
        ->assertHasNoErrors();
}

it('creates a passwordless Client User on first comment and posts immediately', function () {
    Notification::fake();

    $component = Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->assertSet('identified', false);

    identify($component)
        ->assertSet('identified', true)
        ->set('draft', 'The hero copy is too long.')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'hero'])
        ->call('postComment')
        ->assertHasNoErrors();

    $user = User::where('email', 'jane@example.com')->firstOrFail();
    expect($user->role)->toBe(UserRole::Client)
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->toBeNull();

    $comment = $this->artifact->comments()->firstOrFail();
    expect($comment->user_id)->toBe($user->id)
        ->and($comment->isRoot())->toBeTrue()
        ->and($comment->anchor['type'])->toBe('text_range');
});

it('anchors a comment on each artifact type', function (string $factoryState, string $anchorType, array $anchor) {
    $artifact = Artifact::factory()->for($this->project)->{$factoryState}()->create();

    $component = Livewire::test(ArtifactComments::class, ['artifact' => $artifact]);
    identify($component)
        ->set('draft', 'Feedback here.')
        ->set('draftAnchor', $anchor)
        ->call('postComment')
        ->assertHasNoErrors();

    expect($artifact->comments()->firstOrFail()->anchor['type'])->toBe($anchorType);
})->with([
    'markdown text range' => ['markdown', 'text_range', ['type' => 'text_range', 'quote' => 'x']],
    'image region' => ['file', 'image_region', ['type' => 'image_region', 'x' => 12.5, 'y' => 40.0]],
    'html point' => ['html', 'html_point', ['type' => 'html_point', 'x' => 30.0, 'y' => 60.0]],
]);

it('rebinds a repeat commenter with the same email to the same User', function () {
    identify(Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact]))
        ->set('draft', 'First')->set('draftAnchor', ['type' => 'text_range', 'quote' => 'a'])
        ->call('postComment')->assertHasNoErrors();

    // A fresh visit (new component, no session carried) re-identifies with the same email.
    session()->forget('atelier.commenter');

    identify(Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact]), 'Jane Again', 'jane@example.com')
        ->set('draft', 'Second')->set('draftAnchor', ['type' => 'text_range', 'quote' => 'b'])
        ->call('postComment')->assertHasNoErrors();

    expect(User::where('email', 'jane@example.com')->count())->toBe(1)
        ->and($this->artifact->comments()->count())->toBe(2);
});

it('refuses to bind a passwordless commenter to a credentialed account', function () {
    $creator = User::factory()->create(['email' => 'boss@example.com']);

    Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->set('captureName', 'Impostor')
        ->set('captureEmail', 'boss@example.com')
        ->call('saveIdentity')
        ->assertHasErrors('captureEmail')
        ->assertSet('identified', false);

    expect($creator->fresh()->isCreator())->toBeTrue();
});

it('auto-attributes a returning visitor via the remembered cookie', function () {
    $client = User::factory()->client()->create();

    Livewire::withCookies(['atelier_commenter' => (string) $client->id])
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->assertSet('identified', true)
        ->set('draft', 'Back again')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'c'])
        ->call('postComment')
        ->assertHasNoErrors();

    expect($this->artifact->comments()->firstOrFail()->user_id)->toBe($client->id);
});

it('keeps replies one level deep sharing the root thread', function () {
    $client = User::factory()->client()->create();
    $root = Comment::factory()->for($this->artifact)->for($client, 'author')->create();

    Livewire::actingAs($client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->call('startReply', $root->id)
        ->set('replyDraft', 'A reply')
        ->call('reply', $root->id)
        ->assertHasNoErrors();

    $reply = $this->artifact->comments()->where('parent_id', $root->id)->firstOrFail();
    expect($reply->isReply())->toBeTrue()
        ->and($reply->anchor)->toBeNull();

    // Replying to a reply is rejected — reply() only accepts root ids.
    expect(fn () => Livewire::actingAs($client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->call('reply', $reply->id))
        ->toThrow(ModelNotFoundException::class);
});

it('lets only a Creator resolve and reopen a thread', function () {
    $client = User::factory()->client()->create();
    $root = Comment::factory()->for($this->artifact)->for($client, 'author')->create();

    // A client cannot resolve.
    Livewire::actingAs($client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->call('resolve', $root->id)
        ->assertStatus(403);

    expect($root->fresh()->isResolved())->toBeFalse();

    $creator = User::factory()->create();
    Livewire::actingAs($creator)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->call('resolve', $root->id)
        ->assertHasNoErrors();

    expect($root->fresh()->isResolved())->toBeTrue()
        ->and($root->fresh()->resolved_by)->toBe($creator->id);
});

it('lets the author delete their own comment and the Creator delete any', function () {
    $client = User::factory()->client()->create();
    $own = Comment::factory()->for($this->artifact)->for($client, 'author')->create();
    $other = Comment::factory()->for($this->artifact)->create();

    // Author deletes their own.
    Livewire::actingAs($client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->call('deleteComment', $own->id)
        ->assertHasNoErrors();
    expect(Comment::find($own->id))->toBeNull();

    // A client cannot delete someone else's comment.
    Livewire::actingAs($client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->call('deleteComment', $other->id)
        ->assertStatus(403);
    expect(Comment::find($other->id))->not->toBeNull();

    // The Creator can delete any.
    Livewire::actingAs(User::factory()->create())
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->call('deleteComment', $other->id)
        ->assertHasNoErrors();
    expect(Comment::find($other->id))->toBeNull();
});

it('notifies the Creator of a new comment but not the commenting Creator', function () {
    Notification::fake();
    $creator = User::factory()->create();

    identify(Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact]))
        ->set('draft', 'Client feedback')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'a'])
        ->call('postComment')->assertHasNoErrors();

    Notification::assertSentTo($creator, ArtifactCommentPosted::class);

    // The Creator's own comment does not notify themselves.
    Notification::fake();
    Livewire::actingAs($creator)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->set('draft', 'Creator note')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'b'])
        ->call('postComment')->assertHasNoErrors();

    Notification::assertNothingSent();
});

it('shows author names but never emails on the shared page', function () {
    $client = User::factory()->client()->create(['name' => 'Priya Client', 'email' => 'priya@secret.test']);
    Comment::factory()->for($this->artifact)->for($client, 'author')->create(['body' => 'Visible feedback']);

    $this->get(route('project.artifact', [$this->project, $this->artifact]))
        ->assertOk()
        ->assertSee('Priya Client')
        ->assertSee('Visible feedback')
        ->assertDontSee('priya@secret.test');
});

it('backfills the existing operator as a Creator', function () {
    // A plainly-created User (the operator) defaults to the Creator role.
    expect(User::factory()->create()->role)->toBe(UserRole::Creator);
});

it('orders threads newest-first while keeping replies chronological', function () {
    $client = User::factory()->client()->create();

    $old = Comment::factory()->for($this->artifact)->for($client, 'author')
        ->create(['body' => 'Oldest thread', 'created_at' => now()->subDays(3)]);
    $new = Comment::factory()->for($this->artifact)->for($client, 'author')
        ->create(['body' => 'Newest thread', 'created_at' => now()->subDay()]);

    $firstReply = Comment::factory()->replyTo($old)->for($client, 'author')
        ->create(['created_at' => now()->subDays(2)]);
    $secondReply = Comment::factory()->replyTo($old)->for($client, 'author')
        ->create(['created_at' => now()->subHours(6)]);

    $threads = Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->instance()->threads;

    expect($threads->pluck('id')->all())->toBe([$new->id, $old->id])
        ->and($threads->last()->replies->pluck('id')->all())->toBe([$firstReply->id, $secondReply->id]);
});

it('reads the collapsed rail preference from the visitor cookie', function () {
    Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->assertSet('collapsed', false);

    Livewire::withCookies(['atelier_feedback_collapsed' => '1'])
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->assertSet('collapsed', true);
});

it('persists the collapse preference to a long-lived cookie', function () {
    Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->call('setCollapsed', true)
        ->assertSet('collapsed', true);

    $cookie = collect(Cookie::getQueuedCookies())
        ->first(fn ($c) => $c->getName() === 'atelier_feedback_collapsed');

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBe('1');
});

/**
 * Livewire re-evaluates the incoming HTML against a stale Alpine scope before morphing
 * it in, so a bound class on the component root comes back merged with the server's own
 * class list — after a couple of toggles the rail carried both widths and the wider one
 * won. The width class is the server's alone; nothing may bind it on the root.
 */
it('renders exactly one rail width, and never binds it from the root', function () {
    $railClasses = function (string $html): array {
        preg_match('/class="(feedback-rail[^"]*)"/', $html, $match);

        return array_values(array_filter(
            preg_split('/\s+/', $match[1] ?? ''),
            fn (string $class) => str_starts_with($class, 'lg:w-'),
        ));
    };

    $component = Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact]);

    expect($railClasses($component->html()))->toBe(['lg:w-96'])
        ->and($component->html())->not->toMatch('/x-bind:class="[^"]*collapsed/');

    $component->call('setCollapsed', true);

    expect($railClasses($component->html()))->toBe(['lg:w-14']);
});

it('refuses a deactivated email and does not resurrect it as a new user', function () {
    $blocked = User::factory()->client()->deactivated()->create(['email' => 'nuisance@example.com']);

    Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->set('captureName', 'Nuisance')
        ->set('captureEmail', 'nuisance@example.com')
        ->call('saveIdentity')
        ->assertHasErrors('captureEmail')
        ->assertSet('identified', false)
        // The message says the address is unusable, not that someone blocked them.
        ->assertSee('That email cannot be used to comment here.');

    expect(User::where('email', 'nuisance@example.com')->count())->toBe(1)
        ->and($blocked->fresh()->isDeactivated())->toBeTrue()
        ->and($this->artifact->comments()->count())->toBe(0);
});

it('stops honouring a remembered identity once it is deactivated', function () {
    $blocked = User::factory()->client()->deactivated()->create();

    Livewire::withCookies(['atelier_commenter' => (string) $blocked->id])
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->assertSet('identified', false)
        ->set('draft', 'Still here.')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'hero'])
        ->call('postComment')
        ->assertHasErrors('draft');

    expect($this->artifact->comments()->count())->toBe(0);
});

it('keeps a deactivated client’s existing comments visible', function () {
    $blocked = User::factory()->client()->create(['name' => 'Nuisance Nell']);
    Comment::factory()->create([
        'artifact_id' => $this->artifact->id,
        'user_id' => $blocked->id,
        'body' => 'Left before the block.',
    ]);

    $blocked->forceFill(['deactivated_at' => now()])->save();

    Livewire::test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->assertSee('Left before the block.')
        ->assertSee('Nuisance Nell');
});

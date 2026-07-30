<?php

use App\Livewire\Public\ArtifactComments;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ArtifactCommentPosted;
use App\Notifications\ReplyOnYourThread;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushChannel;

beforeEach(function () {
    // These tests submit instantly; the timing floor is not what they are about.
    config(['atelier.comments.min_seconds_before_submit' => 0]);
});

it('adds the web push channel for a subscribed Creator on a new comment', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    $owner->updatePushSubscription('https://push.example/owner', 'p256dh-key', 'auth-token');

    Livewire::test(ArtifactComments::class, ['artifact' => $artifact])
        ->set('captureName', 'Jane')
        ->set('captureEmail', 'jane@example.com')
        ->call('saveIdentity')
        ->set('draft', 'The hero copy is too long.')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'hero'])
        ->call('postComment')
        ->assertHasNoErrors();

    Notification::assertSentTo(
        $owner,
        ArtifactCommentPosted::class,
        fn ($notification, array $channels): bool => in_array(WebPushChannel::class, $channels, true),
    );
});

it('leaves the web push channel off for a Creator who has not subscribed', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    Livewire::test(ArtifactComments::class, ['artifact' => $artifact])
        ->set('captureName', 'Jane')
        ->set('captureEmail', 'jane@example.com')
        ->call('saveIdentity')
        ->set('draft', 'Feedback')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'x'])
        ->call('postComment')
        ->assertHasNoErrors();

    Notification::assertSentTo(
        $owner,
        ArtifactCommentPosted::class,
        fn ($notification, array $channels): bool => ! in_array(WebPushChannel::class, $channels, true),
    );
});

it('pushes ReplyOnYourThread to the subscribed thread author but never the replier', function () {
    Notification::fake();

    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    $author = User::factory()->client()->create();
    $author->updatePushSubscription('https://push.example/author', 'p256dh-key', 'auth-token');
    $replier = User::factory()->client()->create();

    $root = Comment::factory()->for($artifact)->for($author, 'author')->create();

    Livewire::actingAs($replier)->test(ArtifactComments::class, ['artifact' => $artifact])
        ->call('startReply', $root->id)
        ->set('replyDraft', 'Good point.')
        ->call('reply', $root->id)
        ->assertHasNoErrors();

    Notification::assertSentTo($author, ReplyOnYourThread::class);
    Notification::assertNotSentTo($replier, ReplyOnYourThread::class);
});

it('does not push ReplyOnYourThread when the thread author answers themselves', function () {
    Notification::fake();

    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    $author = User::factory()->client()->create();
    $author->updatePushSubscription('https://push.example/author', 'p256dh-key', 'auth-token');

    $root = Comment::factory()->for($artifact)->for($author, 'author')->create();

    Livewire::actingAs($author)->test(ArtifactComments::class, ['artifact' => $artifact])
        ->call('startReply', $root->id)
        ->set('replyDraft', 'Adding a thought.')
        ->call('reply', $root->id)
        ->assertHasNoErrors();

    Notification::assertNotSentTo($author, ReplyOnYourThread::class);
});

it('sends no push to a thread author who has not subscribed a device', function () {
    Notification::fake();

    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    $author = User::factory()->client()->create(); // no subscription
    $replier = User::factory()->client()->create();

    $root = Comment::factory()->for($artifact)->for($author, 'author')->create();

    Livewire::actingAs($replier)->test(ArtifactComments::class, ['artifact' => $artifact])
        ->call('startReply', $root->id)
        ->set('replyDraft', 'Good point.')
        ->call('reply', $root->id)
        ->assertHasNoErrors();

    Notification::assertNotSentTo($author, ReplyOnYourThread::class);
});

it('offers the push opt-in to a Client right after they post a comment', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    Livewire::test(ArtifactComments::class, ['artifact' => $artifact])
        ->assertSet('offerPush', false)
        ->set('captureName', 'Jane')
        ->set('captureEmail', 'jane@example.com')
        ->call('saveIdentity')
        ->set('draft', 'First thoughts')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'x'])
        ->call('postComment')
        ->assertHasNoErrors()
        ->assertSet('offerPush', true)
        ->assertSeeHtml('data-test="post-comment-push-offer"');
});

it('does not offer the Client push prompt to a Creator posting on the stage', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    Livewire::actingAs($owner)->test(ArtifactComments::class, ['artifact' => $artifact])
        ->set('draft', 'Creator note')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'x'])
        ->call('postComment')
        ->assertHasNoErrors()
        ->assertSet('offerPush', false);
});

it('persists a Creator subscription through the authed endpoint', function () {
    $creator = User::factory()->create();

    $this->actingAs($creator)
        ->postJson(route('push.subscribe'), [
            'endpoint' => 'https://push.example/creator-device',
            'keys' => ['p256dh' => 'BPpublic', 'auth' => 'authsecret'],
        ])
        ->assertCreated();

    expect($creator->pushSubscriptions()->where('endpoint', 'https://push.example/creator-device')->exists())->toBeTrue();
});

it('persists a Client subscription through the public endpoint for a recognised commenter', function () {
    $client = User::factory()->client()->create();

    $this->withSession(['atelier.commenter' => (string) $client->id])
        ->postJson(route('push.client.subscribe'), [
            'endpoint' => 'https://push.example/client-device',
            'keys' => ['p256dh' => 'BPpublic', 'auth' => 'authsecret'],
        ])
        ->assertCreated();

    expect($client->pushSubscriptions()->count())->toBe(1);
});

it('refuses a subscription from a visitor the app does not recognise', function () {
    $this->postJson(route('push.client.subscribe'), [
        'endpoint' => 'https://push.example/stranger',
        'keys' => ['p256dh' => 'BPpublic', 'auth' => 'authsecret'],
    ])->assertForbidden();

    expect(PushSubscription::count())->toBe(0);
});

it('drops a subscription through the unsubscribe endpoint', function () {
    $creator = User::factory()->create();
    $creator->updatePushSubscription('https://push.example/gone', 'p256dh-key', 'auth-token');

    $this->actingAs($creator)
        ->postJson(route('push.unsubscribe'), ['endpoint' => 'https://push.example/gone'])
        ->assertOk();

    expect($creator->pushSubscriptions()->count())->toBe(0);
});

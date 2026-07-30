<?php

use App\Livewire\NotificationBell;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ArtifactCommentPosted;
use Livewire\Livewire;

/**
 * A comment on one of the Creator's artifacts, already written to the notifications
 * table the way the app has all along. Returns the pieces the bell reads back.
 *
 * @return array{creator: User, project: Project, artifact: Artifact, comment: Comment}
 */
function seedCreatorNotification(bool $private = false): array
{
    $creator = User::factory()->create();
    $project = Project::factory()->for($creator, 'owner')
        ->{$private ? 'private' : 'public'}()
        ->create(['title' => 'Rebrand']);
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create(['title' => 'Homepage hero']);
    $client = User::factory()->client()->create(['name' => 'Jane Client']);
    $comment = Comment::factory()->for($artifact)->for($client, 'author')->create(['body' => 'Too long.']);

    $creator->notify(new ArtifactCommentPosted($comment));

    return compact('creator', 'project', 'artifact', 'comment');
}

it('shows an unread badge and does not clear it when the panel opens', function () {
    ['creator' => $creator] = seedCreatorNotification();

    Livewire::actingAs($creator)->test(NotificationBell::class)
        ->assertSet('panelOpen', false)
        ->assertSeeHtml('data-test="notification-badge"')
        ->assertSee('1')
        ->call('open')
        ->assertSet('panelOpen', true)
        // Opening lists the event with its author, artifact and project…
        ->assertSee('Jane Client')
        ->assertSee('Homepage hero')
        ->assertSee('Rebrand')
        // …but the badge is untouched: reading is an explicit act.
        ->assertSeeHtml('data-test="notification-badge"');

    expect($creator->unreadNotifications()->count())->toBe(1);
});

it('keeps the list empty until the panel is opened', function () {
    ['creator' => $creator] = seedCreatorNotification();

    Livewire::actingAs($creator)->test(NotificationBell::class)
        ->assertDontSee('Jane Client')
        ->call('open')
        ->assertSee('Jane Client');
});

it('marks one notification read and lands on its artifact when clicked', function () {
    ['creator' => $creator, 'project' => $project, 'artifact' => $artifact] = seedCreatorNotification();
    $id = $creator->notifications()->firstOrFail()->id;

    Livewire::actingAs($creator)->test(NotificationBell::class)
        ->call('open')
        ->call('markRead', $id)
        ->assertRedirect(route('project.artifact', [$project, $artifact]));

    expect($creator->unreadNotifications()->count())->toBe(0)
        ->and($creator->notifications()->firstOrFail()->read_at)->not->toBeNull();
});

it('clears the rest with mark all as read while keeping the rows readable', function () {
    ['creator' => $creator, 'artifact' => $artifact] = seedCreatorNotification();
    $second = Comment::factory()->for($artifact)->create(['body' => 'Second note']);
    $creator->notify(new ArtifactCommentPosted($second));

    expect($creator->unreadNotifications()->count())->toBe(2);

    Livewire::actingAs($creator)->test(NotificationBell::class)
        ->call('open')
        ->call('markAllRead')
        ->assertSee('Homepage hero'); // rows still there to read

    expect($creator->unreadNotifications()->count())->toBe(0)
        ->and($creator->notifications()->count())->toBe(2);
});

it('lets a Creator follow a notification into a private project past the gate', function () {
    ['creator' => $creator, 'project' => $private, 'artifact' => $artifact] = seedCreatorNotification(private: true);
    $id = $creator->notifications()->firstOrFail()->id;

    // The bell sends them to the artifact…
    Livewire::actingAs($creator)->test(NotificationBell::class)
        ->call('open')
        ->call('markRead', $id)
        ->assertRedirect(route('project.artifact', [$private, $artifact]));

    // …and a signed-in Creator is not stopped by the password gate on arrival.
    $this->actingAs($creator)
        ->get(route('project.artifact', [$private, $artifact]))
        ->assertOk();
});

it('is silent for a Creator with nothing to read', function () {
    $creator = User::factory()->create();

    Livewire::actingAs($creator)->test(NotificationBell::class)
        ->assertDontSeeHtml('data-test="notification-badge"')
        ->call('open')
        ->assertSee('No notifications yet.');
});

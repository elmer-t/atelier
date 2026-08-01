<?php

use App\Livewire\Public\ReplyBanner;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

/**
 * A stage artifact carrying one thread the given client started and someone else
 * replied to — the exact shape that leaves an unread reply waiting on that client.
 *
 * @return array{project: Project, artifact: Artifact, client: User, other: User, root: Comment}
 */
function seedUnreadReply(): array
{
    $project = Project::factory()->public()->create(['title' => 'Launch site']);
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create(['title' => 'Landing page']);

    $client = User::factory()->client()->create(['name' => 'Priya']);
    $other = User::factory()->client()->create(['name' => 'Sam']);

    $root = Comment::factory()->for($artifact)->for($client, 'author')->create(['body' => 'What about the CTA?']);
    Comment::factory()->replyTo($root)->for($other, 'author')->create(['body' => 'Tightened it up.']);

    return compact('project', 'artifact', 'client', 'other', 'root');
}

it('shows a dismissible banner to an identified commenter with unread replies', function () {
    ['project' => $project, 'artifact' => $artifact, 'client' => $client] = seedUnreadReply();

    Livewire::withCookies(['atelier_commenter' => (string) $client->id])
        ->test(ReplyBanner::class, ['project' => $project])
        ->assertSeeHtml('data-test="reply-banner"')
        ->assertSee('You have 1 new reply')
        ->assertSeeHtml(route('project.artifact', [$project, $artifact]))
        // The opt-in carries its own refusal, for a browser that turns the subscription down.
        ->assertSeeHtml('data-test="banner-enable-push"')
        ->assertSee('Not available in this browser');
});

it('shows nothing to an anonymous viewer', function () {
    ['project' => $project] = seedUnreadReply();

    Livewire::test(ReplyBanner::class, ['project' => $project])
        ->assertDontSeeHtml('data-test="reply-banner"');
});

it('shows nothing to an identified commenter with zero unread replies', function () {
    // Sam is the replier, so from Sam's seat the thread is not waiting on them.
    ['project' => $project, 'other' => $other] = seedUnreadReply();

    Livewire::withCookies(['atelier_commenter' => (string) $other->id])
        ->test(ReplyBanner::class, ['project' => $project])
        ->assertDontSeeHtml('data-test="reply-banner"');
});

it('counts unread replies across artifacts', function () {
    ['project' => $project, 'client' => $client] = seedUnreadReply();

    // A second artifact with another unread reply on the same client's thread.
    $second = Artifact::factory()->for($project)->markdown('# Two')->create(['title' => 'Pricing']);
    $root = Comment::factory()->for($second)->for($client, 'author')->create();
    Comment::factory()->replyTo($root)->for(User::factory()->client()->create(), 'author')->create();

    Livewire::withCookies(['atelier_commenter' => (string) $client->id])
        ->test(ReplyBanner::class, ['project' => $project])
        ->assertSee('You have 2 new replies');
});

it('hides for the visit once dismissed', function () {
    ['project' => $project, 'client' => $client] = seedUnreadReply();

    Livewire::withCookies(['atelier_commenter' => (string) $client->id])
        ->test(ReplyBanner::class, ['project' => $project])
        ->assertSeeHtml('data-test="reply-banner"')
        ->call('dismiss')
        ->assertSet('dismissed', true)
        ->assertDontSeeHtml('data-test="reply-banner"');

    // The dismissal is remembered in the session, so a fresh mount stays hidden.
    Livewire::withCookies(['atelier_commenter' => (string) $client->id])
        ->test(ReplyBanner::class, ['project' => $project])
        ->assertSet('dismissed', true)
        ->assertDontSeeHtml('data-test="reply-banner"');
});

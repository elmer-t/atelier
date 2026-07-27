<?php

use App\Livewire\Public\ArtifactComments;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->project = Project::factory()->public()->create();
    $this->artifact = Artifact::factory()->for($this->project)->markdown('# Brief')->create(['title' => 'Brief']);

    // The submit-timing floor is exercised by its own tests below; every other
    // test here posts instantly, which is not what it is meant to catch.
    config(['atelier.comments.min_seconds_before_submit' => 0]);
});

function comments(Artifact $artifact)
{
    return Livewire::test(ArtifactComments::class, ['artifact' => $artifact]);
}

function identified(Artifact $artifact, string $email = 'jane@example.com')
{
    return comments($artifact)
        ->set('captureName', 'Jane Doe')
        ->set('captureEmail', $email)
        ->call('saveIdentity')
        ->assertHasNoErrors();
}

/*
|--------------------------------------------------------------------------
| Rate limiting
|--------------------------------------------------------------------------
*/

it('throttles repeated identity capture from one source', function () {
    config(['atelier.comments.rate_limits.identity_per_minute' => 3]);

    // Three distinct addresses are accepted, minting three Client Users.
    foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
        comments($this->artifact)
            ->set('captureName', 'Jane')
            ->set('captureEmail', $email)
            ->call('saveIdentity')
            ->assertHasNoErrors();
    }

    // A fourth visitor from the same address is refused — visibly, but without
    // blowing up the page. (Forgetting the session models a genuinely new visitor;
    // the limiter is keyed on the address they share, not on their session.)
    session()->forget('atelier.commenter');

    comments($this->artifact)
        ->set('captureName', 'Jane')
        ->set('captureEmail', 'd@example.com')
        ->call('saveIdentity')
        ->assertStatus(200)
        ->assertHasErrors('captureEmail')
        ->assertSet('identified', false);

    expect(User::where('email', 'd@example.com')->exists())->toBeFalse()
        ->and(User::whereIn('email', ['a@example.com', 'b@example.com', 'c@example.com'])->count())->toBe(3);
});

it('throttles a single commenter posting in a burst', function () {
    config(['atelier.comments.rate_limits.posts_per_minute_per_commenter' => 2]);

    $component = identified($this->artifact);

    foreach (['One', 'Two'] as $body) {
        $component
            ->set('draft', $body)
            ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'hero'])
            ->call('postComment')
            ->assertHasNoErrors();
    }

    $component
        ->set('draft', 'Three')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'hero'])
        ->call('postComment')
        ->assertStatus(200)
        ->assertHasErrors('draft');

    expect($this->artifact->comments()->count())->toBe(2);
});

it('throttles a flood from one address even as it rotates commenter identities', function () {
    // Per-commenter headroom is generous; the per-IP ceiling is what bites.
    config([
        'atelier.comments.rate_limits.posts_per_minute_per_commenter' => 50,
        'atelier.comments.rate_limits.posts_per_minute_per_ip' => 3,
    ]);

    $first = User::factory()->client()->create();
    $second = User::factory()->client()->create();

    $post = function (User $as, string $body) {
        return Livewire::actingAs($as)
            ->test(ArtifactComments::class, ['artifact' => $this->artifact])
            ->set('draft', $body)
            ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'hero'])
            ->call('postComment');
    };

    $post($first, 'One')->assertHasNoErrors();
    $post($first, 'Two')->assertHasNoErrors();
    $post($second, 'Three')->assertHasNoErrors();

    // A fresh identity buys no fresh budget — the address is out.
    $post($second, 'Four')->assertHasErrors('draft');

    expect($this->artifact->comments()->count())->toBe(3);
});

it('throttles replies on the same budget as root comments', function () {
    config(['atelier.comments.rate_limits.posts_per_minute_per_commenter' => 1]);

    $client = User::factory()->client()->create();
    $root = Comment::factory()->for($this->artifact)->for($client, 'author')->create();

    $reply = fn (string $body) => Livewire::actingAs($client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->set('replyDraft', $body)
        ->call('reply', $root->id);

    $reply('First reply')->assertHasNoErrors();
    $reply('Second reply')->assertHasErrors('replyDraft');

    expect($this->artifact->comments()->where('parent_id', $root->id)->count())->toBe(1);
});

it('tells a throttled visitor how long to wait', function () {
    config(['atelier.comments.rate_limits.identity_per_minute' => 1]);

    comments($this->artifact)
        ->set('captureName', 'Jane')
        ->set('captureEmail', 'first@example.com')
        ->call('saveIdentity')
        ->assertHasNoErrors();

    session()->forget('atelier.commenter');

    comments($this->artifact)
        ->set('captureName', 'Jane')
        ->set('captureEmail', 'second@example.com')
        ->call('saveIdentity')
        ->assertSee('Too many attempts')
        ->assertSee('seconds and try again');
});

it('counts rejected attempts against the limit so invalid input is not free', function () {
    config(['atelier.comments.rate_limits.identity_per_minute' => 2]);

    // Two malformed submissions burn the whole budget.
    foreach (['not-an-email', 'still-not'] as $email) {
        comments($this->artifact)
            ->set('captureName', 'Jane')
            ->set('captureEmail', $email)
            ->call('saveIdentity')
            ->assertHasErrors('captureEmail');
    }

    comments($this->artifact)
        ->set('captureName', 'Jane')
        ->set('captureEmail', 'genuine@example.com')
        ->call('saveIdentity')
        ->assertHasErrors('captureEmail');

    expect(User::where('email', 'genuine@example.com')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Bot mitigation
|--------------------------------------------------------------------------
*/

it('silently drops identity capture when the honeypot is filled', function () {
    comments($this->artifact)
        ->set('captureName', 'Spam Bot')
        ->set('captureEmail', 'bot@example.com')
        ->set('website', 'http://spam.example')
        ->call('saveIdentity')
        // Silent: the bot learns nothing about why nothing happened.
        ->assertHasNoErrors()
        ->assertSet('identified', false);

    expect(User::where('email', 'bot@example.com')->exists())->toBeFalse();
});

it('silently drops a comment when the honeypot is filled', function () {
    identified($this->artifact)
        ->set('draft', 'Buy cheap watches')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'hero'])
        ->set('website', 'http://spam.example')
        ->call('postComment')
        ->assertHasNoErrors();

    expect($this->artifact->comments()->count())->toBe(0);
});

it('silently drops a reply when the honeypot is filled', function () {
    $client = User::factory()->client()->create();
    $root = Comment::factory()->for($this->artifact)->for($client, 'author')->create();

    Livewire::actingAs($client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->set('replyDraft', 'Buy cheap watches')
        ->set('website', 'http://spam.example')
        ->call('reply', $root->id)
        ->assertHasNoErrors();

    expect($this->artifact->comments()->where('parent_id', $root->id)->count())->toBe(0);
});

it('silently drops identity submitted faster than a human could type it', function () {
    config(['atelier.comments.min_seconds_before_submit' => 3]);

    $component = comments($this->artifact)
        ->set('captureName', 'Speed Bot')
        ->set('captureEmail', 'bot@example.com')
        ->call('saveIdentity')
        ->assertHasNoErrors()
        ->assertSet('identified', false);

    expect(User::where('email', 'bot@example.com')->exists())->toBeFalse();

    // The same visitor, having now spent a plausible amount of time on the page.
    $this->travel(4)->seconds();

    $component->call('saveIdentity')->assertSet('identified', true);

    expect(User::where('email', 'bot@example.com')->exists())->toBeTrue();
});

it('renders the honeypot out of sight and out of the tab order', function () {
    $client = User::factory()->client()->create();
    Comment::factory()->for($this->artifact)->for($client, 'author')->create();

    $this->get(route('project.artifact', [$this->project, $this->artifact]))
        ->assertOk()
        ->assertSee('aria-hidden="true"', escape: false)
        ->assertSee('tabindex="-1"', escape: false)
        ->assertSee('autocomplete="off"', escape: false);
});

/*
|--------------------------------------------------------------------------
| Input guards
|--------------------------------------------------------------------------
*/

it('rejects an oversized comment body', function () {
    $limit = (int) config('atelier.comments.max_body_length');

    identified($this->artifact)
        ->set('draft', str_repeat('a', $limit + 1))
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'hero'])
        ->call('postComment')
        ->assertHasErrors('draft');

    expect($this->artifact->comments()->count())->toBe(0);
});

it('rejects an oversized reply body', function () {
    $client = User::factory()->client()->create();
    $root = Comment::factory()->for($this->artifact)->for($client, 'author')->create();

    Livewire::actingAs($client)
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->set('replyDraft', str_repeat('a', (int) config('atelier.comments.max_body_length') + 1))
        ->call('reply', $root->id)
        ->assertHasErrors('replyDraft');

    expect($this->artifact->comments()->where('parent_id', $root->id)->count())->toBe(0);
});

it('validates the captured email before minting a User', function (string $email) {
    $before = User::count();

    comments($this->artifact)
        ->set('captureName', 'Jane Doe')
        ->set('captureEmail', $email)
        ->call('saveIdentity')
        ->assertHasErrors('captureEmail')
        ->assertSet('identified', false);

    expect(User::count())->toBe($before);
})->with([
    'no at sign' => ['janeexample.com'],
    'no domain' => ['jane@'],
    'empty' => [''],
    'overlong' => [str_repeat('a', 250).'@example.com'],
]);

/*
|--------------------------------------------------------------------------
| The frictionless path still works
|--------------------------------------------------------------------------
*/

it('leaves a first-time commenter unaffected under normal use', function () {
    $component = comments($this->artifact)
        ->set('captureName', 'Jane Doe')
        ->set('captureEmail', 'jane@example.com')
        ->call('saveIdentity')
        ->assertHasNoErrors()
        ->assertSet('identified', true);

    $component
        ->set('draft', 'The hero copy is too long.')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'hero'])
        ->call('postComment')
        ->assertHasNoErrors();

    expect($this->artifact->comments()->count())->toBe(1)
        ->and(auth()->check())->toBeFalse();
});

it('leaves a returning commenter unaffected under normal use', function () {
    $client = User::factory()->client()->create();

    Livewire::withCookies(['atelier_commenter' => (string) $client->id])
        ->test(ArtifactComments::class, ['artifact' => $this->artifact])
        ->assertSet('identified', true)
        ->set('draft', 'Back again')
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'hero'])
        ->call('postComment')
        ->assertHasNoErrors();

    expect($this->artifact->comments()->count())->toBe(1);
});

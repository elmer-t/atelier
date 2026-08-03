<?php

use App\Livewire\Public\ArtifactComments;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->project = Project::factory()->public()->create();
    $this->artifact = Artifact::factory()->for($this->project)->markdown('# Brief')->create(['title' => 'Brief']);

    // The submit-timing floor is exercised by its own tests below; every other
    // test here posts instantly, which is not what it is meant to catch.
    config(['atelier.comments.min_seconds_before_submit' => 0]);
});

function openFeedbackRail(Artifact $artifact): Testable
{
    return Livewire::test(ArtifactComments::class, ['artifact' => $artifact]);
}

function identifiedVisitor(Artifact $artifact, string $email = 'jane@example.com'): Testable
{
    return openFeedbackRail($artifact)
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
        openFeedbackRail($this->artifact)
            ->set('captureName', 'Jane')
            ->set('captureEmail', $email)
            ->call('saveIdentity')
            ->assertHasNoErrors();
    }

    // A fourth visitor from the same address is refused — visibly, but without
    // blowing up the page. (Forgetting the session models a genuinely new visitor;
    // the limiter is keyed on the address they share, not on their session.)
    session()->forget('atelier.commenter');

    openFeedbackRail($this->artifact)
        ->set('captureName', 'Jane')
        ->set('captureEmail', 'd@example.com')
        ->call('saveIdentity')
        ->assertSuccessful()
        ->assertHasErrors('captureEmail')
        ->assertSet('identified', false);

    expect(User::where('email', 'd@example.com')->exists())->toBeFalse()
        ->and(User::whereIn('email', ['a@example.com', 'b@example.com', 'c@example.com'])->count())->toBe(3);
});

it('throttles a single commenter posting in a burst', function () {
    config(['atelier.comments.rate_limits.posts_per_minute_per_commenter' => 2]);

    $component = identifiedVisitor($this->artifact);

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
        ->assertSuccessful()
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

    openFeedbackRail($this->artifact)
        ->set('captureName', 'Jane')
        ->set('captureEmail', 'first@example.com')
        ->call('saveIdentity')
        ->assertHasNoErrors();

    session()->forget('atelier.commenter');

    openFeedbackRail($this->artifact)
        ->set('captureName', 'Jane')
        ->set('captureEmail', 'second@example.com')
        ->call('saveIdentity')
        ->assertSee('You sent too many requests')
        ->assertSee('seconds. Then try again.');
});

it('counts rejected attempts against the limit so invalid input is not free', function () {
    config(['atelier.comments.rate_limits.identity_per_minute' => 2]);

    // Two malformed submissions burn the whole budget.
    foreach (['not-an-email', 'still-not'] as $email) {
        openFeedbackRail($this->artifact)
            ->set('captureName', 'Jane')
            ->set('captureEmail', $email)
            ->call('saveIdentity')
            ->assertHasErrors('captureEmail');
    }

    openFeedbackRail($this->artifact)
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
    openFeedbackRail($this->artifact)
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
    identifiedVisitor($this->artifact)
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

    $component = openFeedbackRail($this->artifact)
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

it('fails the timing floor closed when a submission carries no session baseline', function () {
    config(['atelier.comments.min_seconds_before_submit' => 3]);

    $component = openFeedbackRail($this->artifact)
        ->set('captureName', 'Replay Bot')
        ->set('captureEmail', 'bot@example.com');

    // A caller replaying the Livewire snapshot without the session cookie has no
    // baseline to be measured against. Absence must not read as "slow enough".
    session()->forget('atelier.comment_form_opened_at');

    $component->call('saveIdentity')
        ->assertHasNoErrors()
        ->assertSet('identified', false);

    expect(User::where('email', 'bot@example.com')->exists())->toBeFalse();

    // The rejection stamps a baseline, so a human whose session merely lapsed gets
    // through on their next try — while a caller that keeps discarding it never does.
    $this->travel(4)->seconds();

    $component->call('saveIdentity')->assertSet('identified', true);

    expect(User::where('email', 'bot@example.com')->exists())->toBeTrue();
});

it('meters a tripped honeypot against the rate limit', function () {
    config(['atelier.comments.rate_limits.identity_per_minute' => 2]);

    // Two automated submissions are silently dropped — but still cost budget.
    foreach (['bot1@example.com', 'bot2@example.com'] as $email) {
        openFeedbackRail($this->artifact)
            ->set('captureName', 'Spam Bot')
            ->set('captureEmail', $email)
            ->set('website', 'http://spam.example')
            ->call('saveIdentity')
            ->assertHasNoErrors();
    }

    session()->forget('atelier.commenter');

    openFeedbackRail($this->artifact)
        ->set('captureName', 'Jane')
        ->set('captureEmail', 'jane@example.com')
        ->call('saveIdentity')
        ->assertHasErrors('captureEmail');
});

it('renders the honeypot out of sight and out of the tab order', function () {
    $client = User::factory()->client()->create();
    Comment::factory()->for($this->artifact)->for($client, 'author')->create();

    $html = $this->get(route('project.artifact', [$this->project, $this->artifact]))
        ->assertOk()
        ->getContent();

    expect($html)->toBeString();

    // Anchor on the input itself: page-wide string matches are vacuous here,
    // because Flux emits aria-hidden on every spinner it renders.
    expect($html)->toMatch('/<input[^>]*wire:model="website"[^>]*>/');

    preg_match('/<input[^>]*wire:model="website"[^>]*>/', $html, $input);

    expect($input[0])->toContain('tabindex="-1"')
        ->and($input[0])->toContain('autocomplete="off"');

    // …and that the input sits inside a wrapper hidden from assistive tech and
    // dragged off-canvas, so no human is ever offered the field.
    expect($html)->toMatch('/aria-hidden="true"[^>]*class="[^"]*-left-\[9999px\][^"]*"/');
});

/*
|--------------------------------------------------------------------------
| Input guards
|--------------------------------------------------------------------------
*/

it('rejects an oversized comment body', function () {
    $limit = (int) config('atelier.comments.max_body_length');

    identifiedVisitor($this->artifact)
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

    openFeedbackRail($this->artifact)
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
    $component = openFeedbackRail($this->artifact)
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

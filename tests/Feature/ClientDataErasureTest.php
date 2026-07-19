<?php

use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use App\Services\ClientDataEraser;

beforeEach(function () {
    $this->project = Project::factory()->public()->create();
    $this->artifact = Artifact::factory()->for($this->project)->markdown('# Brief')->create();
});

it('anonymises a Client and redacts their comments while preserving thread structure', function () {
    $client = User::factory()->client()->create(['name' => 'Priya Client', 'email' => 'priya@example.com']);
    $other = User::factory()->client()->create();

    $root = Comment::factory()->for($this->artifact)->for($client, 'author')
        ->create(['body' => 'The hero is too long', 'anchor' => ['type' => 'text_range', 'quote' => 'hero']]);
    $ownReply = Comment::factory()->for($this->artifact)->for($client, 'author')
        ->create(['parent_id' => $root->id, 'body' => 'Reach me at priya@example.com']);
    $otherReply = Comment::factory()->for($this->artifact)->for($other, 'author')
        ->create(['parent_id' => $root->id, 'body' => 'I disagree']);

    $summary = app(ClientDataEraser::class)->erase($client);

    expect($summary['comments'])->toBe(2);

    // Identity is anonymised: name replaced, email no longer identifies the person.
    $client->refresh();
    expect($client->name)->toBe(ClientDataEraser::ANONYMISED_NAME)
        ->and($client->email)->not->toBe('priya@example.com')
        ->and($client->email)->toContain('@atelier.invalid');

    // The Client's own comment bodies are redacted, including PII in the text.
    expect($root->fresh()->body)->toBe(ClientDataEraser::REDACTED_BODY)
        ->and($ownReply->fresh()->body)->toBe(ClientDataEraser::REDACTED_BODY)
        ->and($ownReply->fresh()->body)->not->toContain('priya@example.com');

    // Thread structure survives: every row is still present with its relationships,
    // and other people's comments are untouched.
    expect(Comment::count())->toBe(3)
        ->and($root->fresh()->anchor['quote'])->toBe('hero')
        ->and($ownReply->fresh()->parent_id)->toBe($root->id)
        ->and($otherReply->fresh()->body)->toBe('I disagree');
});

it('refuses to erase a non-Client User', function () {
    $creator = User::factory()->create(['name' => 'The Operator']);

    expect(fn () => app(ClientDataEraser::class)->erase($creator))
        ->toThrow(RuntimeException::class);

    expect($creator->fresh()->name)->toBe('The Operator');
});

it('erases a Client by email through the artisan command', function () {
    $client = User::factory()->client()->create(['name' => 'Priya Client', 'email' => 'priya@example.com']);
    Comment::factory()->for($this->artifact)->for($client, 'author')->create(['body' => 'Feedback']);

    $this->artisan('atelier:forget-client', ['email' => 'priya@example.com', '--force' => true])
        ->expectsOutputToContain('redacted 1 comment')
        ->assertExitCode(0);

    expect($client->fresh()->name)->toBe(ClientDataEraser::ANONYMISED_NAME);
});

it('fails the command when no Client matches the email', function () {
    $this->artisan('atelier:forget-client', ['email' => 'nobody@example.com', '--force' => true])
        ->assertExitCode(1);
});

it('will not erase the operator through the command even if the email matches', function () {
    User::factory()->create(['email' => 'boss@example.com']);

    // The Creator is not a Client, so the command treats the email as no-match.
    $this->artisan('atelier:forget-client', ['email' => 'boss@example.com', '--force' => true])
        ->assertExitCode(1);
});

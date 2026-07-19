<?php

use App\Mcp\AgentAbilities;
use App\Mcp\Servers\AtelierAgentServer;
use App\Mcp\Tools\ArtifactRevisions;
use App\Mcp\Tools\CreateMarkdown;
use App\Mcp\Tools\DeleteMarkdown;
use App\Mcp\Tools\FeedbackDigestTool;
use App\Mcp\Tools\ListArtifacts;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\RenameMarkdown;
use App\Mcp\Tools\ReorderArtifacts;
use App\Mcp\Tools\ReplyToThread;
use App\Mcp\Tools\UpdateMarkdown;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use App\Support\Artifacts\MarkdownRevisionWriter;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->project = Project::factory()->create();
});

/**
 * Authenticate the Agent with a Sanctum token carrying the given abilities, then
 * return the server test builder.
 *
 * @param  list<string>  $abilities
 */
function actingAsAgent(User $agent, array $abilities = ['*']): void
{
    Sanctum::actingAs($agent, $abilities);
}

it('creates a markdown artifact establishing Revision 1 authored by the Agent', function () {
    actingAsAgent($this->agent);

    AtelierAgentServer::tool(CreateMarkdown::class, [
        'project' => $this->project->id,
        'title' => 'Agent Brief',
        'body' => '# From the agent',
    ])->assertOk()->assertHasNoErrors();

    $artifact = $this->project->artifacts()->firstOrFail();
    expect($artifact->isMarkdown())->toBeTrue()
        ->and($artifact->revisions()->count())->toBe(1)
        ->and($artifact->body)->toBe('# From the agent')
        ->and($artifact->currentRevision->user_id)->toBe($this->agent->id);
});

it('appends an Agent-attributed Revision on update and no-ops a byte-identical update', function () {
    actingAsAgent($this->agent);
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();

    AtelierAgentServer::tool(UpdateMarkdown::class, ['artifact' => $artifact->id, 'body' => '# Two'])
        ->assertOk()->assertHasNoErrors();

    $artifact->refresh();
    expect($artifact->revisions()->count())->toBe(2)
        ->and($artifact->body)->toBe('# Two')
        ->and($artifact->currentRevision->user_id)->toBe($this->agent->id);

    // Byte-identical update adds no Revision.
    AtelierAgentServer::tool(UpdateMarkdown::class, ['artifact' => $artifact->id, 'body' => '# Two'])
        ->assertOk();

    expect($artifact->refresh()->revisions()->count())->toBe(2);
});

it('renames without a Revision, reorders siblings, and deletes a markdown artifact', function () {
    actingAsAgent($this->agent);
    $a = Artifact::factory()->for($this->project)->markdown('# A')->create(['title' => 'A', 'sort_order' => 1]);
    $b = Artifact::factory()->for($this->project)->markdown('# B')->create(['title' => 'B', 'sort_order' => 2]);

    AtelierAgentServer::tool(RenameMarkdown::class, ['artifact' => $a->id, 'title' => 'Renamed'])
        ->assertOk()->assertHasNoErrors();
    expect($a->refresh()->title)->toBe('Renamed')
        ->and($a->revisions()->count())->toBe(1);

    AtelierAgentServer::tool(ReorderArtifacts::class, ['project' => $this->project->id, 'order' => [$b->id, $a->id]])
        ->assertOk()->assertHasNoErrors();
    expect($this->project->artifacts()->pluck('id')->all())->toBe([$b->id, $a->id]);

    AtelierAgentServer::tool(DeleteMarkdown::class, ['artifact' => $b->id])
        ->assertOk()->assertHasNoErrors();
    expect(Artifact::find($b->id))->toBeNull();
});

it('replies to a Thread attributed to the Agent', function () {
    actingAsAgent($this->agent);
    $artifact = Artifact::factory()->for($this->project)->markdown()->create();
    $root = Comment::factory()->for($artifact)->create();

    AtelierAgentServer::tool(ReplyToThread::class, ['thread' => $root->id, 'body' => 'On it.'])
        ->assertOk()->assertHasNoErrors();

    $reply = $artifact->comments()->where('parent_id', $root->id)->firstOrFail();
    expect($reply->user_id)->toBe($this->agent->id)
        ->and($reply->isReply())->toBeTrue();
});

it('refuses to author against non-markdown artifacts', function () {
    actingAsAgent($this->agent);
    $html = Artifact::factory()->for($this->project)->html()->create();

    AtelierAgentServer::tool(UpdateMarkdown::class, ['artifact' => $html->id, 'body' => '# nope'])
        ->assertHasErrors();
    AtelierAgentServer::tool(DeleteMarkdown::class, ['artifact' => $html->id])
        ->assertHasErrors();

    expect(Artifact::find($html->id))->not->toBeNull();
});

it('never lets an Agent-role user resolve a Thread (the review gate holds)', function () {
    // There is no resolve tool, and the role gate excludes the Agent anyway.
    $artifact = Artifact::factory()->for($this->project)->markdown()->create();
    $root = Comment::factory()->for($artifact)->create();

    expect(Gate::forUser($this->agent)->allows('resolve', $root))->toBeFalse();
});

it('reads projects, artifacts, content and revision history', function () {
    actingAsAgent($this->agent);
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create(['title' => 'Doc']);
    app(MarkdownRevisionWriter::class)->update($artifact, '# One and Two', $this->agent);

    AtelierAgentServer::tool(ListProjects::class)
        ->assertOk()->assertSee($this->project->title);

    AtelierAgentServer::tool(ListArtifacts::class, ['project' => $this->project->id])
        ->assertOk()->assertSee('Doc');

    AtelierAgentServer::tool(ArtifactRevisions::class, ['artifact' => $artifact->id])
        ->assertOk()->assertSee('One and Two');
});

it('pulls a Feedback digest of unresolved threads and human edits since the Agent Revision', function () {
    $human = User::factory()->create();
    actingAsAgent($this->agent);

    $writer = app(MarkdownRevisionWriter::class);
    $artifact = Artifact::factory()->for($this->project)->markdown('# Draft')->create(['title' => 'Spec']);

    // The agent revises, then a human edits after it.
    $writer->update($artifact, "# Draft\nagent line", $this->agent);
    $writer->update($artifact, "# Draft\nhuman line", $human);

    // An unresolved thread and a resolved one.
    Comment::factory()->for($artifact)->create(['body' => 'Please tweak the intro']);
    Comment::factory()->for($artifact)->resolved($human)->create(['body' => 'Already handled']);

    AtelierAgentServer::tool(FeedbackDigestTool::class, ['project' => $this->project->id])
        ->assertOk()
        ->assertSee('Please tweak the intro')
        ->assertDontSee('Already handled')
        ->assertSee('human line');
});

it('refuses a tool when the token lacks the required ability', function () {
    // A read-only token cannot author.
    actingAsAgent($this->agent, [AgentAbilities::ARTIFACT_READ]);

    AtelierAgentServer::tool(CreateMarkdown::class, [
        'project' => $this->project->id,
        'title' => 'Blocked',
        'body' => '# no',
    ])->assertHasErrors();

    expect($this->project->artifacts()->count())->toBe(0);
});

it('refuses a non-Agent user even with a valid token', function () {
    $creator = User::factory()->create();
    actingAsAgent($creator, ['*']);

    AtelierAgentServer::tool(CreateMarkdown::class, [
        'project' => $this->project->id,
        'title' => 'Nope',
        'body' => '# no',
    ])->assertHasErrors();

    expect($this->project->artifacts()->count())->toBe(0);
});

it('refuses an unauthenticated HTTP request to the MCP endpoint', function () {
    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ])->assertUnauthorized();
});

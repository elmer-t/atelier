<?php

use App\Livewire\Admin\Users\Index;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->creator = User::factory()->create(['name' => 'Ada Operator']);
    $this->actingAs($this->creator);
});

it('is reachable by a creator and forbidden to everyone else', function () {
    $this->withoutVite();

    $this->get(route('admin.users'))->assertOk();

    $this->actingAs(User::factory()->client()->create(['email_verified_at' => now()]));
    $this->get(route('admin.users'))->assertForbidden();

    $this->actingAs(User::factory()->agent()->create(['email_verified_at' => now()]));
    $this->get(route('admin.users'))->assertForbidden();
});

it('lists every user with their role and comment count', function () {
    $client = User::factory()->client()->create(['name' => 'Casey Client']);
    Comment::factory()->count(2)->create(['user_id' => $client->id]);
    User::factory()->agent()->create(['name' => 'Atelier Agent']);

    Livewire::test(Index::class)
        ->assertSee('Ada Operator')
        ->assertSee('Casey Client')
        ->assertSee('Atelier Agent')
        ->assertSee('Creator')
        ->assertSee('Client')
        ->assertSee('Agent')
        ->assertCount('users', 3);
});

it('searches by name and by email', function () {
    User::factory()->client()->create(['name' => 'Casey Client', 'email' => 'casey@example.test']);
    User::factory()->client()->create(['name' => 'Robin Reviewer', 'email' => 'robin@example.test']);

    Livewire::test(Index::class)
        ->set('search', 'casey')
        ->assertSee('Casey Client')
        ->assertDontSee('Robin Reviewer')
        ->set('search', 'robin@example')
        ->assertSee('Robin Reviewer')
        ->assertDontSee('Casey Client');
});

it('filters by role', function () {
    User::factory()->client()->create(['name' => 'Casey Client']);

    Livewire::test(Index::class)
        ->set('role', 'client')
        ->assertSee('Casey Client')
        ->assertDontSee('Ada Operator')
        ->call('clearFilters')
        ->assertSet('role', '')
        ->assertSee('Ada Operator');
});

it('ignores a role filter that is not a known enum case', function () {
    Livewire::withQueryParams(['role' => 'nonsense'])
        ->test(Index::class)
        ->assertSee('Ada Operator');
});

it('sorts by name and flips direction when the same column is clicked again', function () {
    User::factory()->client()->create(['name' => 'Zoe Zenith']);

    Livewire::test(Index::class)
        ->call('sort', 'name')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['Ada Operator', 'Zoe Zenith'])
        ->call('sort', 'name')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['Zoe Zenith', 'Ada Operator']);
});

it('sorts by comment count', function () {
    $busy = User::factory()->client()->create(['name' => 'Busy Bea']);
    User::factory()->client()->create(['name' => 'Quiet Quinn']);
    Comment::factory()->count(3)->create(['user_id' => $busy->id]);

    Livewire::test(Index::class)
        ->call('sort', 'comments_count')
        ->assertSeeInOrder(['Busy Bea', 'Quiet Quinn']);
});

it('keeps a hand-edited sort column out of the order by clause', function () {
    Livewire::test(Index::class)
        ->call('sort', 'password')
        ->assertSet('sortBy', 'created_at');

    Livewire::withQueryParams(['sortBy' => 'password'])
        ->test(Index::class)
        ->assertOk()
        ->assertSee('Ada Operator');
});

it('paginates the list and returns to the first page when a filter changes', function () {
    User::factory()->client()->count(20)->create();

    Livewire::test(Index::class)
        ->assertCount('users', 15)
        ->set('paginators.page', 2)
        ->assertCount('users', 6)
        ->set('search', 'a')
        ->assertSet('paginators.page', 1);
});

it('invites a creator by email without opening a registration route', function () {
    Notification::fake();

    Livewire::test(Index::class)
        ->set('inviteName', 'Sam Second')
        ->set('inviteEmail', 'sam@example.test')
        ->call('invite')
        ->assertHasNoErrors();

    $invited = User::where('email', 'sam@example.test')->firstOrFail();

    expect($invited->isCreator())->toBeTrue()
        ->and($invited->password)->toBeNull();

    Notification::assertSentTo($invited, ResetPassword::class);

    expect(Route::has('register'))->toBeFalse();
});

it('rejects an invite for an email that already exists', function () {
    Notification::fake();
    User::factory()->client()->create(['email' => 'taken@example.test']);

    Livewire::test(Index::class)
        ->set('inviteName', 'Sam Second')
        ->set('inviteEmail', 'taken@example.test')
        ->call('invite')
        ->assertHasErrors('inviteEmail');

    Notification::assertNothingSent();
});

it('removes another creator', function () {
    $other = User::factory()->create(['name' => 'Bo Builder']);

    Livewire::test(Index::class)
        ->call('delete', $other->id)
        ->assertHasNoErrors();

    expect(User::find($other->id))->toBeNull();
});

it('refuses to remove yourself', function () {
    User::factory()->create();

    Livewire::test(Index::class)
        ->call('delete', $this->creator->id)
        ->assertForbidden();

    expect($this->creator->fresh())->not->toBeNull();
});

it('refuses to remove the last remaining creator', function () {
    expect(User::where('role', 'creator')->count())->toBe(1);

    Livewire::test(Index::class)
        ->call('delete', $this->creator->id)
        ->assertForbidden();

    expect($this->creator->fresh())->not->toBeNull();
});

it('refuses to delete the agent user', function () {
    $agent = User::factory()->agent()->create();

    Livewire::test(Index::class)
        ->call('delete', $agent->id)
        ->assertForbidden();

    expect($agent->fresh())->not->toBeNull();
});

it('deletes a client and the comments they authored', function () {
    $client = User::factory()->client()->create();
    Comment::factory()->count(2)->create(['user_id' => $client->id]);

    Livewire::test(Index::class)->call('delete', $client->id);

    expect(User::find($client->id))->toBeNull()
        ->and(Comment::where('user_id', $client->id)->count())->toBe(0);
});

it('deactivates and reactivates a client', function () {
    $client = User::factory()->client()->create();

    Livewire::test(Index::class)
        ->call('deactivate', $client->id)
        ->assertHasNoErrors();

    expect($client->fresh()->isDeactivated())->toBeTrue();

    Livewire::test(Index::class)
        ->call('reactivate', $client->id);

    expect($client->fresh()->isDeactivated())->toBeFalse();
});

it('refuses to deactivate a creator or the agent', function () {
    $other = User::factory()->create();
    $agent = User::factory()->agent()->create();

    Livewire::test(Index::class)->call('deactivate', $other->id)->assertForbidden();
    Livewire::test(Index::class)->call('deactivate', $agent->id)->assertForbidden();

    expect($other->fresh()->isDeactivated())->toBeFalse()
        ->and($agent->fresh()->isDeactivated())->toBeFalse();
});

it('renders the full page with sortable headers and the filter controls', function () {
    $this->withoutVite();

    $this->get(route('admin.users'))
        ->assertOk()
        ->assertSee('wire:click="sort(\'name\')"', escape: false)
        ->assertSee('wire:model.live="role"', escape: false);
});

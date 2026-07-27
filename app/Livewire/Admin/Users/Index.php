<?php

namespace App\Livewire\Admin\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The people (and the one Agent) in this install, with role-shaped actions: invite
 * or remove a Creator, deactivate or delete a Client, and the Agent's token elsewhere
 * on its own panel. Every action re-authorizes through {@see UserPolicy} — the
 * row-level buttons only hide what the policy would refuse anyway.
 */
#[Title('Users')]
class Index extends Component
{
    use WithPagination;

    /**
     * The columns the table may be sorted by, mapped to the direction a fresh
     * click starts at — names read best ascending, counts and dates newest-first.
     * Doubles as the allow-list that keeps a hand-edited `?sortBy=` out of the
     * `order by` clause.
     *
     * @var array<string, string>
     */
    private const SORTABLE_COLUMNS = [
        'name' => 'asc',
        'email' => 'asc',
        'role' => 'asc',
        'comments_count' => 'desc',
        'comments_max_created_at' => 'desc',
        'created_at' => 'desc',
    ];

    private const PER_PAGE = 15;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: 'created_at')]
    public string $sortBy = 'created_at';

    #[Url(except: 'desc')]
    public string $sortDirection = 'desc';

    public string $inviteName = '';

    public string $inviteEmail = '';

    /**
     * The `creator` middleware already guards the route; re-asserting the policy
     * here keeps the panel's rules in one place rather than split across layers.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->withCount('comments')
            // "Last active" for a Client is the last thing they said; there is no
            // login to track, and Atelier records views without recording viewers.
            ->withMax('comments', 'created_at')
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->whereLike('name', "%{$this->search}%")
                    ->orWhereLike('email', "%{$this->search}%"),
            ))
            ->when(
                UserRole::tryFrom($this->role),
                fn (Builder $query, UserRole $role) => $query->where('role', $role),
            )
            ->orderBy($this->sortedColumn(), $this->sortedDirection())
            ->paginate(self::PER_PAGE);
    }

    /**
     * Whether any filter narrows the list, which distinguishes "nobody here yet"
     * from "nothing matches".
     */
    #[Computed]
    public function isFiltered(): bool
    {
        return $this->search !== '' || $this->role !== '';
    }

    /**
     * Sort by the given column, flipping the direction when it is already the
     * active one. Unknown columns are ignored.
     */
    public function sort(string $column): void
    {
        if (! array_key_exists($column, self::SORTABLE_COLUMNS)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column
            ? ($this->sortedDirection() === 'asc' ? 'desc' : 'asc')
            : self::SORTABLE_COLUMNS[$column];

        $this->sortBy = $column;

        $this->resetPage();
        unset($this->users);
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'role');

        $this->resetPage();
        unset($this->users);
    }

    /**
     * A narrower result set invalidates whichever page the Creator was on.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Invite a second Creator. The account is minted passwordless and the invitee
     * chooses their own credentials through the existing reset-password flow —
     * no public registration route is opened, and the operator never handles
     * someone else's password. Reaching the reset link proves control of the
     * mailbox, which is what verification would have established, so the address
     * is marked verified rather than sending a second round-trip email.
     */
    public function invite(): void
    {
        $this->authorize('invite', User::class);

        $validated = $this->validate([
            'inviteName' => ['required', 'string', 'max:255'],
            'inviteEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
        ], attributes: ['inviteEmail' => 'email address']);

        $invitee = User::create([
            'name' => $validated['inviteName'],
            'email' => $validated['inviteEmail'],
            'role' => UserRole::Creator,
            'password' => null,
        ]);

        $invitee->forceFill(['email_verified_at' => now()])->save();

        Password::sendResetLink(['email' => $invitee->email]);

        $this->reset('inviteName', 'inviteEmail');
        unset($this->users);

        Flux::modal('invite-creator')->close();
        Flux::toast(variant: 'success', text: __('Invite sent. :name can now set their password.', ['name' => $invitee->name]));
    }

    public function deactivate(User $user): void
    {
        $this->authorize('deactivate', $user);

        $user->forceFill(['deactivated_at' => now()])->save();

        unset($this->users);

        Flux::toast(variant: 'success', text: __('That email can no longer leave feedback. Their comments are untouched.'));
    }

    public function reactivate(User $user): void
    {
        $this->authorize('reactivate', $user);

        $user->forceFill(['deactivated_at' => null])->save();

        unset($this->users);

        Flux::toast(variant: 'success', text: __('Reactivated. They can comment again.'));
    }

    /**
     * Remove a User outright. Their Comments go with them — the `comments` table
     * cascades on `user_id` — which is why a Client is normally deactivated instead.
     */
    public function delete(User $user): void
    {
        $this->authorize('delete', $user);

        $user->delete();

        unset($this->users);

        Flux::toast(variant: 'success', text: __('User deleted.'));
    }

    public function render(): View
    {
        return view('livewire.admin.users.index');
    }

    /**
     * The active sort column, falling back to the default when the query string
     * names something unsortable. The table headers read this rather than the
     * raw property so the highlighted column always matches the `order by`.
     */
    public function sortedColumn(): string
    {
        return array_key_exists($this->sortBy, self::SORTABLE_COLUMNS) ? $this->sortBy : 'created_at';
    }

    /**
     * @return 'asc'|'desc'
     */
    public function sortedDirection(): string
    {
        return $this->sortDirection === 'asc' ? 'asc' : 'desc';
    }
}

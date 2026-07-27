<?php

namespace App\Livewire\Admin\Users;

use App\Http\Middleware\EnsureProjectAccessible;
use App\Livewire\Concerns\ManagesUserAccounts;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One person's page: who they are, everything they have said, and the two actions
 * that apply to a Client — deactivate or delete (#30). There is deliberately no way
 * to edit someone else's name or email here; a Creator edits their own under Settings.
 *
 * This is the only place in the admin area that browses Comments, so each one links
 * out to the artifact it was left on. A signed-in Creator skips the project password
 * gate ({@see EnsureProjectAccessible}), so those links land on the artifact rather
 * than bouncing to the unlock screen.
 */
#[Title('User')]
class Show extends Component
{
    use ManagesUserAccounts;
    use WithPagination;

    public User $user;

    private const PER_PAGE = 20;

    public function mount(User $user): void
    {
        $this->authorize('view', $user);

        $this->user = $user;
    }

    /**
     * Everything this User has written, newest first, with enough of the artifact
     * and project loaded to render a deep link without an N+1.
     *
     * @return LengthAwarePaginator<int, Comment>
     */
    #[Computed]
    public function comments(): LengthAwarePaginator
    {
        return $this->user->comments()
            ->with('artifact.project')
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    public function deactivate(): void
    {
        $this->deactivateAccount($this->user);
    }

    public function reactivate(): void
    {
        $this->reactivateAccount($this->user);
    }

    /**
     * There is nothing left to show once this User is gone, so the Creator lands
     * back on the list.
     */
    public function delete(): void
    {
        $this->deleteAccount($this->user);

        $this->redirectRoute('admin.users', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.users.show');
    }
}

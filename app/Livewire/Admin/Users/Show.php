<?php

namespace App\Livewire\Admin\Users;

use App\Http\Middleware\EnsureProjectAccessible;
use App\Models\Comment;
use App\Models\User;
use Flux\Flux;
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
        $this->authorize('deactivate', $this->user);

        $this->user->forceFill(['deactivated_at' => now()])->save();

        Flux::toast(variant: 'success', text: __('That email can no longer leave feedback. Their comments are untouched.'));
    }

    public function reactivate(): void
    {
        $this->authorize('reactivate', $this->user);

        $this->user->forceFill(['deactivated_at' => null])->save();

        Flux::toast(variant: 'success', text: __('Reactivated. They can comment again.'));
    }

    /**
     * Remove this User along with every Comment they authored — the `comments`
     * table cascades on `user_id`. There is nothing left to show afterwards, so
     * the Creator lands back on the list.
     */
    public function delete(): void
    {
        $this->authorize('delete', $this->user);

        $this->user->delete();

        Flux::toast(variant: 'success', text: __('User deleted, along with their comments.'));

        $this->redirectRoute('admin.users', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.users.show');
    }
}

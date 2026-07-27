<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Policies\UserPolicy;
use Flux\Flux;

/**
 * The three actions the Users panel takes on a person, shared by the list and the
 * detail page so both re-authorize identically and say the same thing afterwards.
 * Each re-checks {@see UserPolicy} rather than trusting that the UI only rendered
 * buttons the policy allows.
 */
trait ManagesUserAccounts
{
    protected function deactivateAccount(User $user): void
    {
        $this->authorize('deactivate', $user);

        $user->deactivate();

        Flux::toast(variant: 'success', text: __('That email can no longer leave feedback. Their comments are untouched.'));
    }

    protected function reactivateAccount(User $user): void
    {
        $this->authorize('reactivate', $user);

        $user->reactivate();

        Flux::toast(variant: 'success', text: __('Reactivated. They can comment again.'));
    }

    /**
     * Remove a User outright. Their Comments go with them — the `comments` table
     * cascades on `user_id` — which is why a Client is normally deactivated instead.
     */
    protected function deleteAccount(User $user): void
    {
        $this->authorize('delete', $user);

        $user->delete();

        Flux::toast(variant: 'success', text: __('User deleted, along with their comments.'));
    }
}

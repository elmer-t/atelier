<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * Recognising the visitor behind a shared project link.
 *
 * Viewing stays anonymous; identity is captured only at comment time and bound to a
 * passwordless Client User, remembered in the session and a year-long return-visit
 * cookie (ADR-0003). Every component on the public stage that needs to know who is
 * acting re-resolves it server-side through here rather than trusting a public
 * property, so a tampered client cannot impersonate another User.
 *
 * Recognition is therefore device-bound: the same person on another browser is a
 * stranger until they comment again. Anything built on it must degrade to "unknown"
 * gracefully rather than claim a person was present.
 */
trait ResolvesCommenter
{
    /** The session key remembering the active commenter across this browsing session. */
    protected const SESSION_KEY = 'atelier.commenter';

    /** The long-lived cookie that re-attributes a returning visitor (ADR-0003). */
    protected const COOKIE_NAME = 'atelier_commenter';

    /**
     * The acting commenter: a logged-in User, else the session/cookie-remembered
     * passwordless Client, else null.
     *
     * A deactivated User is nobody here — otherwise the session or the year-long
     * return-visit cookie would keep letting them act after being cut off (#30).
     */
    protected function currentCommenter(): ?User
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user->isDeactivated() ? null : $user;
        }

        $id = session(self::SESSION_KEY) ?? request()->cookie(self::COOKIE_NAME);

        if ($id === null) {
            return null;
        }

        $remembered = User::query()->whereKey((string) $id)->first();

        if ($remembered === null || $remembered->isDeactivated()) {
            return null;
        }

        if (session(self::SESSION_KEY) === null) {
            session([self::SESSION_KEY => $remembered->id]);
        }

        return $remembered;
    }

    protected function rememberCommenter(User $user): void
    {
        session([self::SESSION_KEY => $user->id]);
        Cookie::queue(self::COOKIE_NAME, (string) $user->id, 60 * 24 * 365);
    }
}

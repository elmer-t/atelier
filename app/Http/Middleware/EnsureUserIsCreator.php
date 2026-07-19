<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the operator area (admin + dashboard) to human Creators.
 *
 * Authentication alone is not enough: passwordless Client and Agent Users
 * (ADR-0003, ADR-0006) share the `users` table, and a Client could acquire a
 * password through the reset flow. This is the authorization boundary that
 * keeps a non-Creator session out of the operator UI even if it obtains one.
 */
class EnsureUserIsCreator
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isCreator(), 403);

        return $next($request);
    }
}

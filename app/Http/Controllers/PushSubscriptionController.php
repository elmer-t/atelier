<?php

namespace App\Http\Controllers;

use App\Livewire\Concerns\ResolvesCommenter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stores and removes the browser push subscriptions that back the native web-push
 * channel (#35). Two doors onto the same store, because consent has two shapes:
 *
 *  - Creators arrive authenticated, so the acting User is the session's own.
 *  - Clients have no login; they are the passwordless commenter behind the ADR-0003
 *    cookie, and only exist as a User once they have left a comment — which is why the
 *    opt-in is offered to them after posting, never before.
 *
 * Either way a subscription is bound to a real User via the package's
 * updatePushSubscription, and web push deliberately bypasses the email-verification
 * gate: the browser's permission grant is the consent, not a verified address.
 */
class PushSubscriptionController extends Controller
{
    use ResolvesCommenter;

    /**
     * Save (or refresh) the authenticated Creator's subscription for this device.
     */
    public function subscribe(Request $request): JsonResponse
    {
        return $this->store($request, $request->user());
    }

    /**
     * Drop the authenticated Creator's subscription for this device.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        return $this->forget($request, $request->user());
    }

    /**
     * Save (or refresh) the recognised commenter's subscription. A visitor the app
     * does not recognise has no User to bind to, so there is nothing to store.
     */
    public function subscribeAsCommenter(Request $request): JsonResponse
    {
        return $this->store($request, $this->currentCommenter());
    }

    /**
     * Drop the recognised commenter's subscription for this device.
     */
    public function unsubscribeAsCommenter(Request $request): JsonResponse
    {
        return $this->forget($request, $this->currentCommenter());
    }

    private function store(Request $request, ?User $user): JsonResponse
    {
        if ($user === null) {
            return response()->json(['message' => __('The system cannot identify you.')], 403);
        }

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $user->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? null,
        );

        return response()->json(['message' => __('Subscribed.')], 201);
    }

    private function forget(Request $request, ?User $user): JsonResponse
    {
        if ($user === null) {
            return response()->json(['message' => __('The system cannot identify you.')], 403);
        }

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        $user->deletePushSubscription($validated['endpoint']);

        return response()->json(['message' => __('Unsubscribed.')]);
    }
}

<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\Events\NotificationFailed;

/**
 * A push the browser's push service turns down leaves no trace of its own: the channel
 * hands the report to a handler that raises this event and otherwise says nothing, so a
 * notification that never arrives looks exactly like one that was never sent. This puts
 * the refusal in the log with the service's own words — the difference between "Atelier
 * did not send it" and "the push service would not take it".
 */
class LogFailedWebPush
{
    public function handle(NotificationFailed $event): void
    {
        Log::warning('Web push rejected by the push service.', [
            'subscription_id' => $event->subscription->getKey(),
            'subscribable_id' => $event->subscription->subscribable_id,
            'push_service' => parse_url((string) $event->subscription->endpoint, PHP_URL_HOST),
            'status' => $event->report->getResponse()?->getStatusCode(),
            'expired' => $event->report->isSubscriptionExpired(),
            'reason' => $event->report->getReason(),
        ]);
    }
}

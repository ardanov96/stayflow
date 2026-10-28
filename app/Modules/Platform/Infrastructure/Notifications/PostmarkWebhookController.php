<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Notifications;

use App\Modules\Platform\Contracts\NotificationDeliveryLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The webhook of the email provider (task P1-15).
 *
 * The provider tells us that a message bounced or was reported as spam.
 * That is the end of the line for that message, and the delivery log has
 * to say so: a notification that bounced is a notification that never
 * arrived.
 *
 * The endpoint is public, so it carries a **shared secret in the path**:
 * the provider is configured with the URL of the webhook, and the token
 * is compared in constant time. The signature scheme of the provider is
 * read from its documentation before the endpoint is opened to the
 * internet (see `docs/VENDORS.md`).
 */
final class PostmarkWebhookController
{
    public function __invoke(Request $request, NotificationDeliveryLog $log, string $token): JsonResponse
    {
        $expected = (string) config('services.postmark.webhook_token', '');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            return response()->json(['status' => 'forbidden'], 403);
        }

        $payload = $request->json()->all();

        $messageId = (string) ($payload['MessageID'] ?? '');
        $recipient = (string) ($payload['Recipient'] ?? '');

        $status = match (strtolower((string) ($payload['Type'] ?? ''))) {
            'bounce' => 'bounced',
            'spamcomplaint' => 'complained',
            default => null,
        };

        if ($status === null || $messageId === '') {
            // A delivery that worked, or a type the log does not care
            // about. Nothing to do.
            return response()->json(['status' => 'ignored']);
        }

        $delivery = $log->markProviderFailure($messageId, $recipient, $status);

        return response()->json(['status' => $delivery === null ? 'unknown' : 'recorded']);
    }
}

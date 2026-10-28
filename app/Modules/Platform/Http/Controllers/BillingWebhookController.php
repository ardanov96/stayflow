<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Application\Billing\ProcessBillingEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The webhook of the billing gateway.
 *
 * The URL carries the reference of the organization and a shared secret:
 * the endpoint is public, and the provider knows nothing about a
 * session.
 */
final class BillingWebhookController
{
    public function __invoke(
        Request $request,
        ProcessBillingEvent $events,
        string $organizationReference,
        string $token,
    ): JsonResponse {
        $expected = (string) config('plans.webhook_token', '');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            return response()->json(['status' => 'forbidden'], 403);
        }

        $outcome = $events->handle(
            organizationReference: $organizationReference,
            payload: (array) $request->json()->all(),
            headers: $this->headers($request),
        );

        return response()->json(['status' => $outcome->value]);
    }

    /**
     * The headers of the request, as plain strings.
     *
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $first = $values[0] ?? null;
            $headers[$name] = $first === null ? '' : (string) $first;
        }

        return $headers;
    }
}

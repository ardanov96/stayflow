<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ValueObjects\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves a per-request id (from the inbound X-Request-Id header when safe,
 * otherwise freshly generated) and:
 *   - shares it on the Log context so every log call in this request carries it,
 *   - echoes it on the response so callers can quote it when reporting issues,
 *   - stashes it on the request for downstream code (controllers, jobs).
 */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = RequestId::fromHeader($request->headers->get(RequestId::HEADER));

        Log::shareContext(['request_id' => $requestId->value]);
        $request->attributes->set('request_id', $requestId->value);

        $response = $next($request);
        $response->headers->set(RequestId::HEADER, $requestId->value);

        return $response;
    }
}

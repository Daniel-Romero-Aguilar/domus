<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSpanishApiMessages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*') || ! str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            return $response;
        }

        $data = json_decode((string) $response->getContent(), true);
        if (! is_array($data)) {
            return $response;
        }

        $translate = function (&$value) use (&$translate): void {
            if (is_array($value)) {
                foreach ($value as &$item) {
                    $translate($item);
                }
            } elseif (is_string($value) && preg_match('/\b(Only|Invalid|successful|successfully|Authentication|Selected|This|You|Task|Loan|Reward|Savings|Unauthenticated|Token|Balance|Family|Guardian|Rejection|Borrower|One or more|Not enough)\b/i', $value)) {
                $value = 'No se pudo completar la operación solicitada.';
            }
        };

        $translate($data);
        return $response->setContent(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}

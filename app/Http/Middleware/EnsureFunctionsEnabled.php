<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFunctionsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->isEnabledFunctions()) {
            return response()->json([
                'message' => 'Tu periodo gratuito ha terminado. Necesitas Premium para usar las funciones de Domus.',
                'code' => 'FUNCTIONS_DISABLED',
                'is_enabled_functions' => false,
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSpanishApiMessages
{
    public function handle(Request $request, Closure $next): Response
    {
        // Cada controlador ya define un mensaje concreto para su resultado.
        // Nunca se altera el contenido de la respuesta: un 2xx debe conservar
        // su mensaje de éxito y un 4xx/5xx debe conservar el motivo real.
        return $next($request);
    }
}

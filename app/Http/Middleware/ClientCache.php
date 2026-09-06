<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientCache
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $maxAge = 300): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $response->headers->set('Cache-Control', "private, max-age={$maxAge}");
        }

        return $response;
    }
}

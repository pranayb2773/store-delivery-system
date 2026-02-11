<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class ForceJsonResponseMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Force JSON responses for API routes
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}

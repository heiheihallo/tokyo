<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpBearerToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.mcp_server.token');

        if (! is_string($expectedToken) || $expectedToken === '') {
            return response('MCP server token is not configured.', 503);
        }

        $authorization = $request->header('Authorization', '');
        $actualToken = Str::startsWith($authorization, 'Bearer ')
            ? Str::after($authorization, 'Bearer ')
            : '';

        if (! is_string($actualToken) || $actualToken === '' || ! hash_equals($expectedToken, $actualToken)) {
            return response('Unauthorized.', 401, [
                'WWW-Authenticate' => 'Bearer',
            ]);
        }

        return $next($request);
    }
}

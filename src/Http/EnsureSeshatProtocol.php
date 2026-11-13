<?php

declare(strict_types=1);

namespace Rekhu\Core\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Protocol 1 only, and X-Rekhu-Protocol on every POST: a form on another site cannot send it. */
class EnsureSeshatProtocol
{
    public function handle(Request $request, Closure $next): Response
    {
        $version = $request->header('X-Rekhu-Protocol');
        if ($version !== null && $version !== '1') {
            return SeshatController::error(400, 'invalid_request_error', 'This server speaks Seshat protocol 1.');
        }
        if ($request->isMethod('POST') && $version !== '1') {
            return SeshatController::error(403, 'permission_error', 'Send X-Rekhu-Protocol: 1.');
        }
        if (! $request->user()) {
            return SeshatController::error(401, 'authentication_error', 'Your session has ended. Reload the page and sign in again.');
        }

        return $next($request);
    }
}

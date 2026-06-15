<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use FalconCms\Core\Models\ApiToken;

/**
 * Authenticates an API request via a personal API token:
 *   Authorization: Bearer <plaintext-token>
 *
 * The token is hashed and matched against api_tokens; on success the request acts as the
 * token's owner, so downstream permission checks ($user->hasPermission(...)) apply exactly
 * as they do in the dashboard.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();
        if (!$bearer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated. Provide a Bearer API token.'], 401);
        }

        $record = ApiToken::where('token', hash('sha256', $bearer))->first();
        if (!$record || !$record->user) {
            return response()->json(['success' => false, 'message' => 'Invalid API token.'], 401);
        }

        $record->forceFill(['last_used_at' => now()])->save();

        $user = $record->user;
        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}

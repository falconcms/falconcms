<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use FalconCms\Core\Models\ApiToken;
use Illuminate\Http\Request;

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

        // Blocking a user has to mean blocking them everywhere. Both dashboard login paths
        // refuse a blocked account; without this the API did not, so an issued token kept
        // working for as long as it existed and "block this user" was not actually true.
        $user = $record->user;
        if ($user->is_blocked || ($user->blocked_until && $user->blocked_until->isFuture())) {
            return response()->json(['success' => false, 'message' => 'This account is blocked.'], 403);
        }

        $record->forceFill(['last_used_at' => now()])->save();

        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}

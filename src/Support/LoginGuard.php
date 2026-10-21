<?php

namespace FalconCms\Core\Support;

use App\Models\User;
use FalconCms\Core\Models\BlockedIp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The sign-in lockout every login form shares: the dashboard's, the shop's My Account and
 * plugins' own (the wholesale login).
 *
 * Per account: five wrong passwords lock it for 30 minutes (`users.login_attempts` /
 * `blocked_until`) whichever form they were typed into, so switching forms does not buy an
 * attacker more guesses. Per address: an IP the dashboard login blocked (BlockedIp) is refused
 * on every form, and a storefront form cools an address down after too many failures of any
 * kind — many accounts tried from one place.
 */
final class LoginGuard
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 30;

    /** Storefront: failed sign-ins from one address before it has to wait. */
    public const ADDRESS_MAX_FAILURES = 20;

    /** Whether the dashboard login has blocked this address. */
    public static function ipBlocked(string $ip): bool
    {
        return BlockedIp::where('ip_address', $ip)->where('attempts', '>=', self::MAX_ATTEMPTS)->exists();
    }

    /** Why this account may not sign in now, or null. An expired lock is cleared. */
    public static function refusal(?User $user): ?string
    {
        if (!$user) {
            return null;
        }
        $until = $user->getAttribute('blocked_until');
        if ($user->getAttribute('is_blocked') && !$until) {
            return 'Your account has been permanently blocked.';
        }
        if ($until) {
            $until = Carbon::parse($until);
            if ($until->isFuture()) {
                $minutes = max(1, (int) ceil(now()->diffInSeconds($until, true) / 60));

                return "Too many failed attempts. Your account is temporarily blocked. Please try again after {$minutes} minutes.";
            }
            // Lock over: start counting again.
            $user->update(['login_attempts' => 0, 'blocked_until' => null]);
        }

        return null;
    }

    /** A wrong password for an existing account. Returns the tries left; 0 means it was just locked. */
    public static function failed(User $user, string $ip): int
    {
        $user->increment('login_attempts');
        $attempts = (int) $user->getAttribute('login_attempts');
        if ($attempts >= self::MAX_ATTEMPTS) {
            $user->update(['blocked_until' => now()->addMinutes(self::LOCK_MINUTES), 'last_failed_login_ip' => $ip]);

            return 0;
        }

        return self::MAX_ATTEMPTS - $attempts;
    }

    /** A right password: the account's count starts over and the address is forgiven. */
    public static function succeeded(User $user, string $ip): void
    {
        $user->update(['login_attempts' => 0, 'blocked_until' => null, 'last_failed_login_ip' => null]);
        BlockedIp::where('ip_address', $ip)->delete();
    }

    /** Seconds this address must still wait on a storefront form (0: it may try). */
    public static function addressWait(string $ip): int
    {
        $key = self::addressKey($ip);

        return RateLimiter::tooManyAttempts($key, self::ADDRESS_MAX_FAILURES) ? RateLimiter::availableIn($key) : 0;
    }

    /** A failed storefront sign-in from this address (known account or not). */
    public static function addressFailed(string $ip): void
    {
        RateLimiter::hit(self::addressKey($ip), self::LOCK_MINUTES * 60);
    }

    private static function addressKey(string $ip): string
    {
        return 'falcon-login-address:'.$ip;
    }
}

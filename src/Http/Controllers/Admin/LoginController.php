<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

use FalconCms\Core\Models\BlockedIp;
use FalconCms\Core\Mail\PasswordResetMail;

class LoginController extends Controller
{
    public function showLoginForm(Request $request)
    {
        // Check IP Block
        if (BlockedIp::where('ip_address', $request->ip())->where('attempts', '>=', 5)->exists()) {
            abort(403, 'You do not have permission to access this page. Your IP has been blocked.');
        }

        if (Auth::check()) {
            return redirect()->route('admin.dashboard.index');
        }

        $theme = get_cms_option('login_theme', 'modern');

        if ($theme === 'funny') {
            return view('falcon-cms::admin.auth.login-funny');
        }

        return view('falcon-cms::admin.auth.login-modern');
    }

    public function checkCredentials(Request $request)
    {
        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        $isValid = Auth::validate($credentials);

        return response()->json([
            'valid' => $isValid,
            'email_exists' => User::where('email', $request->email)->exists()
        ]);
    }

    public function login(Request $request)
    {
        // Check IP Block
        if (BlockedIp::where('ip_address', $request->ip())->where('attempts', '>=', 5)->exists()) {
            abort(403, 'You do not have permission to access this page. Your IP has been blocked.');
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Check manual permanent block
            if ($user->is_blocked && !$user->blocked_until) {
                return back()->withErrors(['email' => 'Your account has been permanently blocked.'])->onlyInput('email');
            }

            // Check temporary block
            if ($user->blocked_until) {
                if ($user->blocked_until->isFuture()) {
                    $diffInSeconds = now()->diffInSeconds($user->blocked_until);
                    if ($diffInSeconds > 0) {
                        $minutes = ceil($diffInSeconds / 60);
                        return back()->withErrors([
                            'email' => "Too many failed attempts. Your account is temporarily blocked. Please try again after {$minutes} minutes."
                        ])->onlyInput('email');
                    }
                } else {
                    // Block expired, reset attempts but keep log
                    $user->update(['login_attempts' => 0, 'blocked_until' => null]);
                }
            }
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            // Re-check block just in case session/auth mismatch
            if ($user->is_blocked || ($user->blocked_until && $user->blocked_until->isFuture())) {
                Auth::logout();
                return back()->withErrors(['email' => 'Your account is restricted.'])->onlyInput('email');
            }

            // Block sign-in until the email address has been verified.
            if (is_null($user->email_verified_at)) {
                Auth::logout();
                $request->session()->put('pending_verification_email', $user->email);
                return redirect()->route('admin.verify.notice')
                    ->with('error', 'Please verify your email address before signing in. You can resend the link below.');
            }


            // Reset attempts on successful login
            $user->update([
                'login_attempts' => 0,
                'blocked_until' => null,
                'last_failed_login_ip' => null
            ]);

            // Clear IP attempts if any
            BlockedIp::where('ip_address', $request->ip())->delete();
            
            $request->session()->regenerate();

            // Device limit. The "limit devices" option inverts to a cap:
            //   - unchecked (off) => unlimited concurrent devices, never block
            //   - checked   (on)  => cap concurrent sessions at max_devices (minimum 1)
            $limitEnabled = get_cms_option('allow_multi_device', '0') === '1';

            if ($limitEnabled) {
                $maxDevices = max(1, (int) get_cms_option('max_devices', 3));

                $userSessions = \Illuminate\Support\Facades\DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $request->session()->getId())
                    ->orderBy('last_activity', 'desc')
                    ->get();

                if ($userSessions->count() >= $maxDevices) {
                    if ($user->hasRole('super-admin')) {
                        // Kick the oldest (least recently active) session to make room
                        $sessionToKill = $userSessions->last();
                        \Illuminate\Support\Facades\DB::table('sessions')
                            ->where('id', $sessionToKill->id)
                            ->delete();
                    } else {
                        Auth::logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                        return back()->withErrors([
                            'email' => "Login denied: Maximum device limit ($maxDevices) reached for this account."
                        ])->onlyInput('email');
                    }
                }
            }

            return redirect()->intended(route('admin.dashboard.index'));
        }

        // On Failure
        if ($user) {
            $user->increment('login_attempts');
            $attemptsLeft = 5 - $user->login_attempts;
            
            if ($user->login_attempts >= 5) {
                $user->update([
                    'blocked_until' => now()->addMinutes(30),
                    'last_failed_login_ip' => $request->ip()
                ]);
                return back()->withErrors(['email' => 'Too many failed attempts. Your account and IP have been blocked for 30 minutes.'])->onlyInput('email');
            }

            return back()->withErrors([
                'email' => "Invalid credentials. You have {$attemptsLeft} attempts left before your account is blocked.",
            ])->onlyInput('email');
        } else {
            // Unregistered user attempt
            $ipRecord = BlockedIp::firstOrCreate(['ip_address' => $request->ip()]);
            $ipRecord->increment('attempts');
            
            if ($ipRecord->attempts >= 5) {
                $geoData = $this->getCountryFromIp($request->ip());
                $ipRecord->update([
                    'reason' => 'Too many attempts with non-existent emails',
                    'country' => $geoData['name'],
                    'country_code' => $geoData['code'],
                ]);
                abort(403, 'Too many failed attempts. Your IP has been permanently blocked.');
            }

            $attemptsLeft = 5 - $ipRecord->attempts;
            return back()->withErrors([
                'email' => "Invalid credentials. You have {$attemptsLeft} attempts left before your IP is blocked.",
            ])->onlyInput('email');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    private function getCountryFromIp($ip)
    {
        try {
            $response = @file_get_contents("http://ip-api.com/json/{$ip}");
            if ($response) {
                $data = json_decode($response, true);
                return [
                    'name' => $data['country'] ?? 'Unknown',
                    'code' => isset($data['countryCode']) ? strtolower($data['countryCode']) : null
                ];
            }
        } catch (\Exception $e) {}
        return ['name' => 'Unknown', 'code' => null];
    }

    public function showForgotPasswordForm()
    {
        $theme = get_cms_option('login_theme', 'modern');

        if ($theme === 'funny') {
            return view('falcon-cms::admin.auth.forgot-password-funny');
        }

        return view('falcon-cms::admin.auth.forgot-password-modern');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $genericMessage = 'If that email is registered, you will receive a reset link shortly. Please check your inbox (and spam folder).';

        $user = \App\Models\User::where('email', $request->email)->first();
        if (!$user) {
            return back()->with('status', $genericMessage);
        }

        // Generate token and store (expires in 5 minutes — checked on reset)
        $token = \Illuminate\Support\Str::random(60);
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email'      => $request->email,
                'token'      => \Illuminate\Support\Facades\Hash::make($token),
                'created_at' => now(),
            ]
        );

        $resetUrl = route('admin.password.reset', ['token' => $token, 'email' => $request->email]);

        try {
            Mail::to($request->email)->send(new PasswordResetMail($resetUrl, $user->name ?? ''));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Password reset mail failed: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::info("Password reset link (mail failed) for {$request->email}: {$resetUrl}");
        }

        return back()->with('status', $genericMessage);
    }

    public function showResetForm(Request $request, $token)
    {
        $theme = get_cms_option('login_theme', 'modern');
        $email = $request->email;

        $viewData = ['token' => $token, 'email' => $email];

        if ($theme === 'funny') {
            return view('falcon-cms::admin.auth.reset-password-funny', $viewData);
        }

        return view('falcon-cms::admin.auth.reset-password-modern', $viewData);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $record = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !\Illuminate\Support\Facades\Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'This password reset token is invalid.']);
        }

        // Token expires after 5 minutes
        if (\Illuminate\Support\Carbon::parse($record->created_at)->addMinutes(5)->isPast()) {
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->withErrors(['email' => 'This password reset link has expired. Please request a new one.']);
        }

        $user = \App\Models\User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'We could not find a user with that email address.']);
        }

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->password)
        ]);

        // Delete token
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('admin.login')->with('success', 'Your password has been reset successfully!');
    }

    public function requestAdminMagicLink(Request $request)
    {
        if (!get_cms_option('magic_login_enabled')) {
            return back()->withErrors(['email' => 'Magic login is not enabled.']);
        }

        $request->validate(['email' => ['required', 'email:rfc,dns']]);

        $email = strtolower(trim($request->email));
        $user  = User::where('email', $email)->first();

        if ($user && !$user->is_blocked && optional($user->role)->slug !== 'customer') {
            \Illuminate\Support\Facades\DB::table('magic_login_tokens')
                ->where('email', $email)
                ->whereNull('used_at')
                ->delete();

            $rawToken = \Illuminate\Support\Str::random(48);
            $hash     = hash('sha256', $rawToken);

            \Illuminate\Support\Facades\DB::table('magic_login_tokens')->insert([
                'email'      => $email,
                'token'      => $hash,
                'expires_at' => now()->addMinutes(10),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $magicUrl = route('admin.magic.verify', ['token' => $rawToken]);

            try {
                Mail::to($email)->send(new \FalconCms\Core\Mail\MagicLoginMail($magicUrl, $user->name));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Admin magic login mail failed: ' . $e->getMessage());
                \Illuminate\Support\Facades\Log::info("Admin magic login link (mail failed) for {$email}: {$magicUrl}");
            }
        }

        return back()->with('magic_sent', true);
    }

    public function verifyAdminMagicLink(Request $request, string $token)
    {
        if (BlockedIp::where('ip_address', $request->ip())->where('attempts', '>=', 5)->exists()) {
            abort(403, 'Your IP has been blocked.');
        }

        $hash = hash('sha256', $token);

        $row = \Illuminate\Support\Facades\DB::table('magic_login_tokens')
            ->where('token', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This magic link is invalid or has expired. Please request a new one.']);
        }

        $user = User::where('email', $row->email)->first();

        if (!$user || $user->is_blocked || optional($user->role)->slug === 'customer') {
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Access denied for this account.']);
        }

        \Illuminate\Support\Facades\DB::table('magic_login_tokens')
            ->where('token', $hash)
            ->update(['used_at' => now()]);

        Auth::login($user, false);
        $request->session()->regenerate();

        $user->update(['login_attempts' => 0, 'blocked_until' => null, 'last_failed_login_ip' => null]);
        BlockedIp::where('ip_address', $request->ip())->delete();

        return redirect()->intended(route('admin.dashboard.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}

<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use FalconCms\Core\Mail\EmailVerificationMail;

class RegisterController extends Controller
{
    /** Minutes a verification link stays valid. */
    protected int $verifyTtl = 5;

    public function showRegistrationForm()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard.index');
        }

        if (get_cms_option('users_can_register', '0') !== '1') {
            return redirect()->route('admin.login')->with('error', 'Registration is currently disabled.');
        }

        $theme = get_cms_option('registration_theme', 'modern');

        if ($theme === 'funny') {
            return view('falcon-cms::admin.auth.register-funny');
        }

        return view('falcon-cms::admin.auth.register-modern');
    }

    public function checkEmail(Request $request)
    {
        $exists = User::where('email', $request->email)->exists();
        return response()->json(['exists' => $exists]);
    }

    public function register(Request $request)
    {
        if (get_cms_option('users_can_register', '0') !== '1') {
            return redirect()->route('admin.login');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Assign the role configured in Settings → "New User Default Role",
        // falling back to subscriber if it is unset or no longer exists.
        $defaultRoleSlug = get_cms_option('default_role', 'subscriber');
        $defaultRole = \FalconCms\Core\Models\Role::where('slug', $defaultRoleSlug)->first()
            ?: \FalconCms\Core\Models\Role::where('slug', 'subscriber')->first();

        $user = User::create([
            'name' => $request->name,
            'username' => $this->uniqueUsername($request->email),
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $defaultRole ? $defaultRole->id : null,
            'email_verified_at' => null, // must verify before logging in
        ]);

        // Do NOT log the user in. Send a time-limited verification link instead.
        $this->sendVerificationLink($user);

        $request->session()->put('pending_verification_email', $user->email);

        return redirect()->route('admin.verify.notice')
            ->with('success', 'Account created! We sent a verification link to ' . $user->email . '. Please confirm your email to sign in.');
    }

    /** Notice page shown after registration / when an unverified user tries to log in. */
    public function verifyNotice(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard.index');
        }
        $email = $request->session()->get('pending_verification_email', '');
        return view('falcon-cms::admin.auth.verify-notice', compact('email'));
    }

    /** Verify the email from a signed link, then sign the user in. */
    public function verifyEmail(Request $request, $id, $hash)
    {
        if (!$request->hasValidSignature()) {
            return redirect()->route('admin.verify.notice')
                ->with('error', 'This verification link is invalid or has expired. Please request a new one below.');
        }

        $user = User::find($id);
        if (!$user || !hash_equals(sha1($user->email), (string) $hash)) {
            return redirect()->route('admin.login')->with('error', 'Invalid verification link.');
        }

        if ($user->email_verified_at) {
            return redirect()->route('admin.login')->with('success', 'Your email is already verified. Please sign in.');
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $request->session()->forget('pending_verification_email');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard.index')->with('success', 'Email verified — welcome aboard!');
    }

    /** Re-send a fresh verification link. */
    public function resendVerification(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->first();

        // Always behave the same way to avoid leaking which emails exist.
        if ($user && is_null($user->email_verified_at)) {
            $this->sendVerificationLink($user);
        }

        $request->session()->put('pending_verification_email', $request->email);

        return redirect()->route('admin.verify.notice')
            ->with('success', 'If that email needs verifying, a new link is on its way. It expires in ' . $this->verifyTtl . ' minutes.');
    }

    /** Build a unique, sanitized username from the email local part (e.g. john@a.com → john, john1…). */
    protected function uniqueUsername(string $email): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9_]/i', '', strstr($email, '@', true) ?: ''));
        if ($base === '') $base = 'user';

        $username = $base;
        $i = 1;
        while (User::where('username', $username)->exists()) {
            $username = $base . $i;
            $i++;
        }

        return $username;
    }

    /** Build a signed, time-limited verification URL and email it to the user. */
    protected function sendVerificationLink(User $user): void
    {
        $verifyUrl = URL::temporarySignedRoute(
            'admin.verify.email',
            now()->addMinutes($this->verifyTtl),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        try {
            Mail::to($user->email)->send(new EmailVerificationMail($verifyUrl, $user->name ?? '', $this->verifyTtl));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Email verification send failed for ' . $user->email . ': ' . $e->getMessage());
        }
    }
}

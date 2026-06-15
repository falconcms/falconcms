<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
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

        $subscriberRole = \FalconCms\Core\Models\Role::where('slug', 'subscriber')->first();

        $user = User::create([
            'name' => $request->name,
            'username' => strstr($request->email, '@', true), // Simple username from email
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $subscriberRole ? $subscriberRole->id : null,
        ]);

        Auth::login($user);

        return redirect()->route('admin.dashboard.index')->with('success', 'Registered successfully!');
    }
}

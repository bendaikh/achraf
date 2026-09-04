<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $user = Auth::user();

            if (! $user->isAccountActive()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Ce compte est inactif ou suspendu. Contactez un administrateur.',
                ])->withInput($request->only('email'));
            }

            $user->forceFill(['last_login_at' => now()])->save();
            $request->session()->regenerate();

            if ($user->isSuperAdmin()) {
                return redirect()->intended('dashboard')->with('success', 'Welcome back, Super Admin!');
            }

            return redirect()->intended('dashboard')->with('success', 'Welcome back!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('login')->with('success', 'You have been logged out successfully.');
    }

    public function dashboard()
    {
        return view('dashboard');
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\SmtpSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        SmtpSettings::applyToConfig();

        // Always return a generic message — never reveal whether the email exists.
        $status = Password::broker()->sendResetLink(
            $request->only('email')
        );

        // Even on failure (user unknown / throttle), show the same UX message.
        return back()->with('status', 'Si un compte correspond à cette adresse, un e-mail de réinitialisation a été envoyé.');
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
        }

        throw ValidationException::withMessages([
            'email' => ['Ce lien de réinitialisation est invalide ou a expiré.'],
        ]);
    }
}

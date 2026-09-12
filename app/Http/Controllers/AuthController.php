<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Log;
use Lunaweb\RecaptchaV3\Facades\RecaptchaV3;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'g-recaptcha-response' => ['required', 'recaptchav3:login,0.5']
        ]);

        $score = RecaptchaV3::verify($request->get('g-recaptcha-response'), 'login');

        if ($score > 0.5) {
            $credentials = $request->only(['email', 'password']);
            if (Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
                $request->session()->regenerate();
                return redirect()->intended(route('dashboard'));
            }
        } else {
            return abort(403, 'Error de validación de reCAPTCHA. Por favor, inténtelo de nuevo.');
        }

        return back()
            ->withErrors(['email' => 'Las credenciales no coinciden o el usuario esta inactivo.'])
            ->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

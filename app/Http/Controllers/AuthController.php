<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                 $rules = [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];

        if (config('recaptchav3.secret') && config('recaptchav3.sitekey')) {
            $rules['g-recaptcha-response'] = ['required', 'recaptchav3:login,0.5'];
        }

        $request->validate($rules, [
            'g-recaptcha-response.required' => 'No se pudo generar la verificación de seguridad. Habilita JavaScript y vuelve a intentarlo.',
            'g-recaptcha-response.recaptchav3' => 'La verificación anti-robot falló. Vuelve a intentarlo.',
        ]);

        $credentials = $request->only(['email', 'password']);
        if (Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
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

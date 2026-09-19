<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
        $rules = [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];

        if (config('recaptchav3.secret') && config('recaptchav3.sitekey')) {
            $rules['g-recaptcha-response'] = ['required', 'recaptchav3:login,0.5'];
        }

        $credentials = $request->validate($rules, [
            'g-recaptcha-response.required' => 'No se pudo generar la verificación de seguridad. Habilita JavaScript y vuelve a intentarlo.',
            'g-recaptcha-response.recaptchav3' => 'La verificación anti-robot falló. Vuelve a intentarlo.',
        ]);

        $throttleKey = 'admin-login:' . strtolower($credentials['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()
                ->withErrors(['email' => 'Demasiados intentos de acceso. Inténtalo de nuevo en ' . $seconds . ' segundos.'])
                ->onlyInput('email');
        }

        if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey, 120);

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

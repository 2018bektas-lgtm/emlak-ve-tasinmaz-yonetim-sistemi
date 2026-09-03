<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'giris' => ['required', 'string'],
            'sifre' => ['required', 'string'],
        ], [
            'giris.required' => 'E-posta veya kullanıcı adı zorunludur.',
            'sifre.required' => 'Şifre zorunludur.',
        ]);

        $this->ensureIsNotRateLimited($request);

        $alan = filter_var($data['giris'], FILTER_VALIDATE_EMAIL) ? 'mail' : 'kullanici_adi';

        $credentials = [
            $alan => $data['giris'],
            'password' => $data['sifre'],
        ];

        if (! Auth::attempt($credentials, $request->boolean('beni_hatirla'))) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'giris' => 'Girdiğiniz bilgiler kayıtlarımızla eşleşmiyor.',
            ]);
        }

        if (! Auth::user()?->aktif_mi) {
            Auth::logout();

            throw ValidationException::withMessages([
                'giris' => 'Hesabınız pasif duruma alınmış. Yöneticinizle iletişime geçin.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        return redirect()->intended(route('panel.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'giris' => 'Çok fazla başarısız deneme. Lütfen '.$seconds.' saniye sonra tekrar deneyin.',
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('giris')).'|'.$request->ip());
    }
}

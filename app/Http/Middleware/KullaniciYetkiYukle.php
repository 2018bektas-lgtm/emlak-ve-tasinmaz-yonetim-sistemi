<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class KullaniciYetkiYukle
{
    public function handle(Request $request, Closure $next): Response
    {
        $kullanici = $request->user();
        if (! $kullanici) {
            return $next($request);
        }

        if (! $kullanici->aktif_mi) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'giris' => 'Hesabınız pasif duruma alınmış. Yöneticinizle iletişime geçin.',
            ]);
        }

        $kullanici->loadMissing(['rol.izinler', 'mudurluk']);

        return $next($request);
    }
}

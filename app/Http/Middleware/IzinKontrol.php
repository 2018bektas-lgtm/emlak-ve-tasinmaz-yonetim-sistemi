<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IzinKontrol
{
    public function handle(Request $request, Closure $next, string $izin): Response
    {
        $kullanici = $request->user();
        if (! $kullanici || ! $kullanici->izinVarMi($izin)) {
            abort(403, 'Bu işlem için yetkiniz yok.');
        }

        return $next($request);
    }
}

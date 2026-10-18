<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kat mülkiyetli kayıtların ".../bb/{bolum}" rotalarında {bolum} parametresini
 * request attribute'una taşır ve rotadan siler. Böylece controller metotları
 * (mahalleTkgmId, ada, parsel, [hisse|yapi]) imzalarını değiştirmeden hem
 * parsel hem bölüm rotalarında çalışır. Okumak için: TasinmazYetkisi::adrestekiBolum().
 */
class TasinmazBolumParametresi
{
    public const ATTRIBUTE = 'tasinmaz_bolum';

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        if ($route && $route->hasParameter('bolum')) {
            $request->attributes->set(self::ATTRIBUTE, (string) $route->parameter('bolum'));
            $route->forgetParameter('bolum');
        }

        return $next($request);
    }
}

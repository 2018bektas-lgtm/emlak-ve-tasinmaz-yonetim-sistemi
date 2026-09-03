<?php

use App\Http\Middleware\IzinKontrol;
use App\Http\Middleware\KullaniciYetkiYukle;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/');
        $middleware->redirectUsersTo('/panel');
        $middleware->alias([
            'izin' => IzinKontrol::class,
            'yetki.yukle' => KullaniciYetkiYukle::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

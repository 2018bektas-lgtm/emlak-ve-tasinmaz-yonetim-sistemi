<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testler yalnızca phpunit.xml'deki bellek içi SQLite veritabanında çalışır.
     * Ayarlar önbellekteyse (php artisan config:cache) phpunit.xml'deki DB_* değerleri
     * yok sayılır ve RefreshDatabase gerçek veritabanını sıfırlar — bu yüzden
     * başka bir veritabanı görülürse trait'ler çalışmadan test durdurulur.
     */
    protected function setUpTraits()
    {
        $baglanti = config('database.default');
        $veritabani = config("database.connections.{$baglanti}.database");
        if ($baglanti !== 'sqlite' || $veritabani !== ':memory:') {
            throw new RuntimeException(
                "Testler gerçek veritabanında çalışmaz ({$baglanti}: {$veritabani}). "
                .'Ayarlar önbellekte olabilir: php artisan config:clear'
            );
        }

        return parent::setUpTraits();
    }
}

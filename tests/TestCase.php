<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testler yalnızca kendilerine ayrılmış bir veritabanında çalışır: bellek içi
     * SQLite ya da adı `_test` ile biten bir veritabanı (phpunit.xml'deki DB_*).
     *
     * Ayarlar önbellekteyse (php artisan config:cache) phpunit.xml'deki DB_* değerleri
     * yok sayılır ve RefreshDatabase gerçek veritabanını sıfırlar — bu yüzden başka
     * bir veritabanı görülürse trait'ler çalışmadan test durdurulur.
     */
    protected function setUpTraits()
    {
        $baglanti = config('database.default');
        $veritabani = (string) config("database.connections.{$baglanti}.database");

        $bellekIciSqlite = $baglanti === 'sqlite' && $veritabani === ':memory:';
        $ayriTestVeritabani = str_ends_with($veritabani, '_test');

        if (! $bellekIciSqlite && ! $ayriTestVeritabani) {
            throw new RuntimeException(
                "Testler gerçek veritabanında çalışmaz ({$baglanti}: {$veritabani}). "
                .'Testler için `_test` ile biten ayrı bir veritabanı kullanın. '
                .'Ayarlar önbellekte olabilir: php artisan config:clear'
            );
        }

        return parent::setUpTraits();
    }
}

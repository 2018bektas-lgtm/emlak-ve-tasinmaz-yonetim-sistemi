<?php

namespace Tests\Unit;

use App\Support\CevreAnalizi;
use PHPUnit\Framework\TestCase;

class CevreAnaliziTest extends TestCase
{
    public function test_abb_adlari_turkce_baslik_bicimine_cevrilir(): void
    {
        // mb_convert_case "KIZILAY"ı "Kizilay" yapıyordu.
        $this->assertSame('Kızılay Parkı', CevreAnalizi::baslikBicimi('ABB  KIZILAY PARKI'));
        $this->assertSame('Sıhhiye İstasyonu', CevreAnalizi::baslikBicimi('SIHHİYE İSTASYONU'));
        $this->assertSame('Toros Sk-Celal Bayar Blv', CevreAnalizi::baslikBicimi('TOROS SK-CELAL BAYAR BLV'));
    }

    public function test_seviye_esikleri(): void
    {
        $this->assertSame('Çok iyi', CevreAnalizi::seviye(75));
        $this->assertSame('İyi', CevreAnalizi::seviye(55));
        $this->assertSame('Orta', CevreAnalizi::seviye(35));
        $this->assertSame('Zayıf', CevreAnalizi::seviye(34));
    }
}

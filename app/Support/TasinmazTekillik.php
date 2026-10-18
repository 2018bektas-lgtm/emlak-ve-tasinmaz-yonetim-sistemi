<?php

namespace App\Support;

use App\Enums\KayitTipi;
use App\Models\Tasinmaz;

/**
 * Aynı taşınmazın iki kez kaydedilmesini engelleyen kurallar.
 *
 *  - Kat mülkiyetli BBN: her bağımsız bölüm ayrı tapulu, ayrı kayıttır.
 *    Kimlik = mahalle + ada + parsel + blok + BBN no. Blok opsiyoneldir
 *    (blok olmayan binalarda boş kalır); "A blok 10" ile "B blok 10" farklıdır.
 *  - Boş parsel / Kat mülkiyetsiz bina: tapuda tek ana taşınmaz kaydı vardır.
 *    Kimlik = mahalle + ada + parsel.
 *
 * Karşılaştırma normalize edilmiş değerlerle yapılır: büyük/küçük harf,
 * boşluk, "BLOK" eki ve sayılardaki baştaki sıfırlar fark yaratmaz.
 */
class TasinmazTekillik
{
    /**
     * Çakışan kaydı döndürür; çakışma yoksa null.
     */
    public static function cakisanKayit(
        int $mahalleId,
        string $ada,
        string $parsel,
        ?string $kayitTipi,
        ?string $blokNo = null,
        ?string $bbnNo = null,
        ?int $haricId = null,
    ): ?Tasinmaz {
        $ada = self::numara($ada);
        $parsel = self::numara($parsel);
        $katMulkiyetli = $kayitTipi === KayitTipi::KatMulkiyetli->value;
        $blok = self::blok($blokNo);
        $bbn = self::numara($bbnNo);

        $adaylar = Tasinmaz::query()
            ->where('mahalle_id', $mahalleId)
            ->when($haricId, fn ($q) => $q->whereKeyNot($haricId))
            ->with('yapi')
            ->get(['id', 'mahalle_id', 'ada', 'parsel', 'kayit_tipi']);

        return $adaylar->first(function (Tasinmaz $t) use ($ada, $parsel, $katMulkiyetli, $blok, $bbn) {
            if (self::numara($t->ada) !== $ada || self::numara($t->parsel) !== $parsel) {
                return false;
            }

            $digeriKatMulkiyetli = $t->kayit_tipi === KayitTipi::KatMulkiyetli;

            // Bağımsız bölüm kayıtları yalnızca birbirleriyle, parsel kayıtları
            // yalnızca birbirleriyle çakışır.
            if ($katMulkiyetli !== $digeriKatMulkiyetli) {
                return false;
            }
            if (! $katMulkiyetli) {
                return true;
            }

            return self::blok($t->yapi?->blok_no) === $blok
                && self::numara($t->yapi?->bagimsiz_bolum_no) === $bbn;
        });
    }

    /**
     * Kat mülkiyetsiz binada aynı blok + BBN no'nun listede tekrar edip
     * etmediğini kontrol eder. Tekrar eden satırların indekslerini döndürür.
     *
     * @param  array<int, array<string, mixed>>  $yapilar
     * @return array<int, int>
     */
    public static function tekrarEdenYapilar(array $yapilar): array
    {
        $gorulen = [];
        $tekrarlar = [];
        foreach (array_values($yapilar) as $i => $y) {
            $bbn = self::numara($y['bagimsiz_bolum_no'] ?? null);
            if ($bbn === '') {
                continue;
            }
            $anahtar = self::blok($y['blok_no'] ?? null).'|'.$bbn;
            if (isset($gorulen[$anahtar])) {
                $tekrarlar[] = $i;
            }
            $gorulen[$anahtar] = true;
        }

        return $tekrarlar;
    }

    /** "a blok", "A Blok", "A-BLOĞU", "Blok A", " a " → "A" */
    public static function blok(?string $deger): string
    {
        $s = preg_replace('/[\s\-_.]+/u', '', self::buyukHarf($deger)) ?? '';
        $s = preg_replace('/^(BLOĞU|BLOGU|BLOK)|(BLOĞU|BLOGU|BLOK)$/u', '', $s) ?? $s;

        return $s;
    }

    /** " 010 " → "10", "12a" → "12A" */
    public static function numara(?string $deger): string
    {
        $s = preg_replace('/\s+/u', '', self::buyukHarf($deger)) ?? '';
        if ($s !== '' && ctype_digit($s)) {
            $s = ltrim($s, '0');

            return $s === '' ? '0' : $s;
        }

        return $s;
    }

    private static function buyukHarf(?string $deger): string
    {
        $s = trim((string) $deger);

        return mb_strtoupper(strtr($s, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
    }

    /**
     * Kullanıcıya gösterilecek açıklama metni.
     */
    public static function etiket(?string $blokNo, ?string $bbnNo): string
    {
        $blok = trim((string) $blokNo);
        $parca = $blok !== '' ? $blok.' Blok, ' : '';

        return $parca.'BB No '.trim((string) $bbnNo);
    }
}

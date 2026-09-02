<?php

namespace Database\Seeders;

use App\Models\ImarDurumu;
use Illuminate\Database\Seeder;

class ImarDurumuSeeder extends Seeder
{
    private array $durumlar = [
        'AÇIK SPOR TESİSİ ALANI',
        'AÇIK SPOR TESİSLERİ ALANI',
        'AĞAÇLANDIRILACAK ALAN',
        'AİLE SAĞLIĞI MERKEZİ',
        'AİLE YAŞAM MERKEZİ',
        'AİLE YAŞAM MERKEZİ VE KREŞ ALANI',
        'AKARYAKIT +LPG',
        'AKARYAKIT VE SERVİS İSTASYONU ALANI',
        'ANAOKULU ALANI',
        'ANAOKULU+KREŞ',
        'ARSA',
        'BELEDİYE HİZMET ALANI',
        'BELEDİYE HİZMET ALANI + CAMİ ALANI',
        'BELEDİYE HİZMET ALANI (İTFAİYE)',
        'BELEDİYE HİZMET ALANI (MEZBAHA)',
        'BÖLGE PARKI',
        'BÖLGE PARKI VE REKREASYON ALANI',
        'BÖLGESEL PARK VE SPOR ALANI',
        'CAMİ',
        'CAMİ ALANI',
        'CAMİ+SKT',
        'ÇOCUK BAHÇESİ VE OYUN ALANI',
        'DEPOLAMA ALANI',
        'DİNİ TESİS ALANLARI',
        'DİNİ TESİSLER ALANI',
        'DOĞALGAZ REGLAJ İSTASYONU',
        'DOĞAL KARAKTERİ KORUNACAK ALAN',
        'EĞİTİM ALANI',
        'EĞİTİM TESİSİ (ÇIRAKLIK OKULU, MYO)',
        'ENERJİ ÜRETİM ALANI',
        'FUAR ALANI',
        'FUAR PANAYIR VE FESTİVAL ALANI',
        'GAZ VE ABONE İŞLERİ MÜDÜRLÜĞÜ ALANI',
        'GELİŞME KONUT ALANI',
        'GENÇLİK MERKEZİ',
        'GENEL OTOPARK ALANI',
        'HALK EĞİTİM MERKEZİ',
        'HASTANE',
        'İÇME SUYU TESİSLERİ ALANI (DEPOLAMA-ARITMA-TERFİ MERKEZİ)',
        'İDARİ HİZMET ALANI',
        'İDARİ TESİS ALANI',
        'İLKOKUL ALANI',
        'İLKÖĞRETİM TESİS ALANLARI',
        'İLKÖĞRETİM TESİSLERİ ALANI',
        'KAPALI SPOR TESİSİ ALANI',
        'KARMA KULLANIM',
        'KATI ATIK TESİSLERİ ALANI (BOŞALTMA, BERTARAF, İŞLEME, TRANSFER VE DEPOLAMA)',
        'KENTSEL ALTYAPI ALANI',
        'KENTSEL ÇALIŞMA ALANI',
        'KENTSEL SERVİS ALANI',
        'KENTSEL SERVİS ALANLARI',
        'KENTSEL VE BÖLGESEL İŞ MERKEZİ',
        'KIRSAL YERLEŞME ALANI',
        'KONGRE VE SERGİ MERKEZİ ALANI',
        'KONUT',
        'KONUT ALANI',
        'KONUT DIŞI KENTSEL ÇALIŞMA ALANI',
        'KONUT+TİCARET',
        'KREŞ ALANI',
        'KREŞ-GÜNDÜZ BAKIMEVİ',
        'KÜÇÜK SANAYİ ALANI',
        'KÜLTÜR EĞLENCE PARKI',
        'KÜLTÜREL EĞLENCE',
        'KÜLTÜREL EĞLENCE ALANI',
        'KÜLTÜREL TESİS ALANI',
        'LİSE ALANI',
        'LOJİSTİK MERKEZ',
        'LOJİSTİK TESİS ALANI',
        'MERA',
        'MERKEZ ALANI',
        'MERKEZİ İŞ ALANI',
        'MESKEN ALANI',
        'MESLEKİ VE TEKNİK ÖĞRETİM TESİSİ ALANI',
        'MEYDAN ALANI VE YERALTI OTOPARKI',
        'MEZARLIK',
        'MEZARLIK ALANI',
        'MİLLET BAHÇESİ',
        'ORTAOKUL ALANI',
        'ORTA YOĞUNLUKLU KONUT ALANI',
        'OTEL ALANI',
        'OTOPARK',
        'OYUN VE SPOR ALANI',
        'ÖZEL PROJE ALANI',
        'ÖZEL SAĞLIK ALANI',
        'ÖZEL SAĞLIK TESİSİ ALANI',
        'ÖZEL SOSYAL ALTYAPI ALANI',
        'ÖZEL SOSYAL TESİS ALANI',
        'ÖZEL SOSYO KÜLTÜREL TESİS ALANI',
        'ÖZEL SPOR ALANI',
        'ÖZEL SPOR TESİS ALANI',
        'PARK',
        'PARK ALANI',
        'PARK VE YEŞİL ALAN',
        'PARK YEŞİL ALAN',
        'PASİF YEŞİL ALAN',
        'PAZAR ALANI',
        'RAYLI TOPLU TAŞIMA İSTASYONU',
    ];

    public function run(): void
    {
        if (ImarDurumu::query()->exists()) {
            $this->command?->info('ImarDurumu tablosu bos degil, seed atlandi.');

            return;
        }

        $sira = 0;
        foreach ($this->durumlar as $ad) {
            ImarDurumu::create([
                'ad' => $ad,
                'sira' => $sira++,
                'aktif_mi' => true,
            ]);
        }

        $this->command?->info('ImarDurumu: '.ImarDurumu::count().' kayit eklendi.');
    }
}

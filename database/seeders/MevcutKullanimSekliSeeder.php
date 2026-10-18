<?php

namespace Database\Seeders;

use App\Models\MevcutKullanimSekli;
use Illuminate\Database\Seeder;

/**
 * Taşınmaz formundaki "mevcut kullanım şekli" başlangıç listesi.
 * (Önceden tablo migration'ının içinde ekleniyordu.)
 */
class MevcutKullanimSekliSeeder extends Seeder
{
    private array $sekiller = [
        'OTOPARK', 'BELEDİYE ARŞİV', 'BELEDİYE DÖKÜM ALANI', 'BELEDİYE TUZ SİLOSU', 'AÇIK PAZAR YERİ',
        'ADLİYE BİNASİ', 'AFAD', 'AĞAÇLIK ALAN', 'AHIR', 'AHŞAP EV',
        'AİLE YAŞAM MERKEZİ', 'AKARYAKIT İSTASYONU', 'ALIŞVERİŞ MERKEZİ', 'ANAOKULU', 'ARAZİ',
        'ARSA', 'ASKERİ BÖLGE', 'ATIK DEPOLAMA ALANI', 'ATIK SU ARITMA TESİSİ', 'BAĞ',
        'BAĞ BAHÇE', 'BAHÇE', 'BANKA', 'BELEDİYE HİZMET ALANI', 'BİNA',
        'BİNA (DAİRE)', 'BİNA (ŞANTİYE)', 'BİNA VE ARSA', 'CAMİ', 'CAMİ İMAM EVİ MEZARLIK',
        'CAMİ LOJMANI', 'CAMİ VE ARSA', 'CAMİ VE MEZARLIK', 'CAMİ VE PARK', 'ÇAYIR',
        'ÇEŞME', 'ÇOCUK BAKIM EVİ', 'ÇOCUK GÜNDÜZ BAKIM EVİ', 'ÇOCUK KULUBÜ', 'DEPO',
        'DERNEK', 'DİĞER', 'DİREK YERİ',
    ];

    public function run(): void
    {
        foreach ($this->sekiller as $i => $ad) {
            MevcutKullanimSekli::query()->updateOrCreate(['ad' => $ad], ['aktif_mi' => true, 'sira' => $i + 1]);
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mevcut_kullanim_sekilleri', function (Blueprint $table) {
            $table->id();
            $table->string('ad', 150)->unique();
            $table->boolean('aktif_mi')->default(true);
            $table->unsignedSmallInteger('sira')->default(0);
            $table->timestamps();
            $table->index('aktif_mi');
        });

        $now = now();
        $baslangicListe = [
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

        $satirlar = [];
        foreach ($baslangicListe as $i => $ad) {
            $satirlar[] = [
                'ad' => $ad,
                'aktif_mi' => true,
                'sira' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('mevcut_kullanim_sekilleri')->insert($satirlar);
    }

    public function down(): void
    {
        Schema::dropIfExists('mevcut_kullanim_sekilleri');
    }
};

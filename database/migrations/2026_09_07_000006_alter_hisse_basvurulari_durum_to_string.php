<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * durum enum -> varchar; süreç aşamaları genişledi (referans projeyle
     * aynı akış: Tebligat / Değerleme / Encümen / Ödeme / Tapu / Tamamlandı).
     */
    public function up(): void
    {
        Schema::table('hisse_basvurulari', function (Blueprint $table) {
            $table->string('durum', 40)->default('basvuruldu')->change();
        });
    }

    public function down(): void
    {
        Schema::table('hisse_basvurulari', function (Blueprint $table) {
            $table->enum('durum', [
                'basvuruldu',
                'incelemede',
                'onaylandi',
                'reddedildi',
                'tamamlandi',
            ])->default('basvuruldu')->change();
        });
    }
};

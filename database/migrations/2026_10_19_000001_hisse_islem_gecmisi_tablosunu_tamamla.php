<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * hisse_islem_gecmisi tablosu 2026_09_07_000001 migration'ına sonradan
 * eklendiği için o migration'ı daha önce çalıştırmış veritabanlarında
 * oluşmadı; hisse satışı dosya ekranı 42S02 ile düşüyordu. Tablo zaten
 * varsa dokunulmaz, yoksa aynı şemayla oluşturulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hisse_islem_gecmisi')) {
            return;
        }

        Schema::create('hisse_islem_gecmisi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grup_no')->index();
            $table->unsignedBigInteger('basvuru_id')->nullable();
            $table->foreignId('kullanici_id')->nullable()->constrained('kullanicilar')->nullOnDelete();
            $table->string('islem', 60);
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Tablonun asıl sahibi 2026_09_07_000001; burada geri alınacak bir şey yok.
    }
};

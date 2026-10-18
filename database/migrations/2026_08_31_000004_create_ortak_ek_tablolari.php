<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polimorfik ekler (sahip: taşınmaz ya da yapı): tapu kaydı, meclis
 * kararları ve resimler. Ayrıca kullanıcıların liste kolon tercihleri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tapular', function (Blueprint $table) {
            $table->id();
            $table->string('sahip_type');
            $table->unsignedBigInteger('sahip_id');
            $table->string('takbis_zemin_no', 30)->index();
            $table->string('cilt_no', 20);
            $table->string('sayfa_no', 20);
            $table->string('tapu_durumu', 20)->default('aktif')->index();
            $table->string('tapu_kaydi_pdf', 500)->nullable();
            $table->date('tapu_tarihi')->nullable();
            $table->timestamps();
            $table->unique(['sahip_type', 'sahip_id'], 'tapular_sahip_unique');
        });

        Schema::create('meclis_kararlari', function (Blueprint $table) {
            $table->id();
            $table->string('sahip_type');
            $table->unsignedBigInteger('sahip_id');
            $table->string('karar_tipi', 30)->index();
            $table->string('karar_no', 50);
            $table->date('karar_tarihi');
            $table->text('karar_ozeti')->nullable();
            $table->string('karar_pdf', 500)->nullable();
            $table->timestamps();
            $table->index(['sahip_type', 'sahip_id', 'karar_tarihi'], 'meclis_kararlari_sahip_tarih_index');
        });

        Schema::create('resimler', function (Blueprint $table) {
            $table->id();
            $table->string('sahip_type');
            $table->unsignedBigInteger('sahip_id');
            $table->string('dosya_yolu', 500);
            $table->string('kucuk_yolu', 500)->nullable();
            $table->smallInteger('sira')->default(0);
            $table->boolean('kapak_mi')->default(false);
            $table->string('aciklama')->nullable();
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('boyut');
            $table->timestamps();
            $table->index(['sahip_type', 'sahip_id', 'sira'], 'resimler_sahip_sira_index');
        });

        Schema::create('kullanici_kolon_tercihleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kullanici_id')->constrained('kullanicilar')->cascadeOnDelete();
            $table->string('tablo_anahtari', 40);
            $table->json('kolonlar');
            $table->timestamps();
            $table->unique(['kullanici_id', 'tablo_anahtari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kullanici_kolon_tercihleri');
        Schema::dropIfExists('resimler');
        Schema::dropIfExists('meclis_kararlari');
        Schema::dropIfExists('tapular');
    }
};

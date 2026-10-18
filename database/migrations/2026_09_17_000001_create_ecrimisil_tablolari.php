<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ecrimisil (izinsiz kullanım): işgalci kaydı, tutanaklar, raporlar, resimler.
 * İşgal durumu tutanaklardan türetilir (bitişi boş tutanak = işgal sürüyor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecrimisil_isgalciler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->nullable()->index()->constrained('tasinmazlar')->nullOnDelete();
            $table->foreignId('kullanici_id')->nullable()->index()->constrained('kullanicilar')->nullOnDelete();
            $table->string('ad_soyad_unvan', 200)->index()->comment('Gerçek kişi ad-soyad veya tüzel kişi ünvan');
            $table->string('tc_vergi_no', 20)->nullable()->comment('TC (11) veya Vergi No (10)');
            $table->string('adres', 500)->nullable();
            $table->string('cadde_sokak', 200)->nullable();
            $table->string('nitelik', 150)->nullable()->comment('Bakkal, büfe, satış ofisi vb. — işgalin niteliği');
            $table->json('koordinat')->nullable()->comment('İşgal alanının poligon geometrisi');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });

        Schema::create('ecrimisil_tutanaklari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isgalci_id')->index()->constrained('ecrimisil_isgalciler')->cascadeOnDelete();
            $table->string('seri_no', 100)->nullable()->unique();
            $table->date('tutanak_tarihi')->nullable();
            $table->date('isgal_baslangic_tarihi')->nullable();
            $table->date('isgal_bitis_tarihi')->nullable()->comment('Boşsa devam ediyor');
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });

        Schema::create('ecrimisil_raporlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isgalci_id')->index()->constrained('ecrimisil_isgalciler')->cascadeOnDelete();
            $table->string('rapor_no', 100)->nullable()->index();
            $table->date('rapor_tarihi')->nullable();
            $table->decimal('fiyat', 15, 2)->nullable()->comment('Rapor bedeli (₺)');
            $table->string('dosya_yolu', 500)->nullable()->comment('PDF veya Word ek');
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });

        Schema::create('ecrimisil_resimleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isgalci_id')->index()->constrained('ecrimisil_isgalciler')->cascadeOnDelete();
            $table->foreignId('tutanak_id')->nullable()->constrained('ecrimisil_tutanaklari')->nullOnDelete();
            $table->string('dosya_yolu', 500);
            $table->string('aciklama', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecrimisil_resimleri');
        Schema::dropIfExists('ecrimisil_raporlari');
        Schema::dropIfExists('ecrimisil_tutanaklari');
        Schema::dropIfExists('ecrimisil_isgalciler');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lojman: taşınmaz envanterindeki bir birimden açılan konut, başvurular,
 * tahsisler ve evraklar. Konum alanları bağlı taşınmazdan kopyalanır
 * (taşınmaz düzeltilince eşitlenir). "Dolu" durumu aktif tahsisten türetilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lojmanlar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->nullable()->index()->constrained('tasinmazlar')->nullOnDelete();
            $table->foreignId('il_id')->nullable()->constrained('iller')->nullOnDelete();
            $table->foreignId('ilce_id')->nullable()->constrained('ilceler')->nullOnDelete();
            $table->unsignedBigInteger('mahalle_id')->nullable();
            $table->string('ada', 20)->nullable();
            $table->string('parsel', 20)->nullable();
            $table->string('ad', 200)->comment('Lojman adı — örn. "Merkez Lojmanları A Blok Daire 3"');
            $table->string('blok', 50)->nullable();
            $table->string('daire_no', 50)->nullable();
            $table->smallInteger('kat')->nullable()->comment('0 = zemin, negatif = bodrum');
            $table->unsignedTinyInteger('oda_sayisi')->nullable()->comment('Örn. 3 (3+1 için)');
            $table->decimal('alan_m2', 10, 2)->nullable();
            $table->string('tip', 40)->nullable()->comment('Özel / Görev / Sıra / Hizmet Tahsisli');
            $table->string('durum', 20)->default('bos')->index()->comment('bos | dolu | isgalde | bakimda | kullanim-disi (dolu tahsisten türetilir)');
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index(['mahalle_id', 'ada', 'parsel']);
            $table->foreign('mahalle_id')->references('id')->on('mahalleler')->nullOnDelete();
        });

        Schema::create('lojman_basvurulari', function (Blueprint $table) {
            $table->id();
            // Boşsa genel başvuru (herhangi bir boş lojmana tahsis edilebilir)
            $table->foreignId('lojman_id')->nullable()->index()->constrained('lojmanlar')->nullOnDelete();
            $table->string('ad_soyad', 200);
            $table->string('tc_kimlik', 11)->nullable()->index();
            $table->string('sicil_no', 50)->nullable()->index();
            $table->string('unvan', 150)->nullable();
            $table->string('birim', 200)->nullable();
            $table->string('tahsis_turu', 40)->nullable()->comment('Özel / Görev / Sıra / Hizmet Tahsisli');
            $table->date('basvuru_tarihi');
            $table->string('durum', 30)->default('basvuruldu')->index()
                ->comment('basvuruldu | degerlendirmede | onaylandi | reddedildi | tahsisedildi');
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });

        Schema::create('lojman_tahsisleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lojman_id')->index()->constrained('lojmanlar')->cascadeOnDelete();
            $table->foreignId('basvuru_id')->nullable()->index()->constrained('lojman_basvurulari')->nullOnDelete();
            $table->string('ad_soyad', 200);
            $table->string('sicil_no', 50)->nullable();
            $table->date('baslangic_tarihi');
            $table->date('bitis_tarihi')->nullable()->comment('Boşsa devam ediyor');
            $table->string('karar_no', 100)->nullable();
            $table->date('karar_tarihi')->nullable();
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });

        // Lojman silinince başvuruya ait evraklar kalır (lojman_id boşalır)
        Schema::create('lojman_evraklari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basvuru_id')->nullable()->index()->constrained('lojman_basvurulari')->cascadeOnDelete();
            $table->foreignId('lojman_id')->nullable()->index()->constrained('lojmanlar')->nullOnDelete();
            $table->string('kategori', 30)->index()->comment('basvuru | gorus-gelen | gorus-giden | encumen | tahsis | diger');
            $table->string('evrak_no', 100)->nullable();
            $table->date('evrak_tarihi')->nullable();
            $table->string('dosya_yolu', 500)->nullable();
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lojman_evraklari');
        Schema::dropIfExists('lojman_tahsisleri');
        Schema::dropIfExists('lojman_basvurulari');
        Schema::dropIfExists('lojmanlar');
    }
};

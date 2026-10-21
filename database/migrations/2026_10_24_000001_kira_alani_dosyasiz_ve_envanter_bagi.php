<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kira alanı artık kayıt akışının merkezi: önce kira taşınmazı (alan)
 * eklenir, kiraya verildiğinde dosya numarası bağlanır.
 *
 * - Boş bir alanın henüz dosyası olmayabilir: kira_dosya_id boş bırakılabilir.
 *   Dosya silinirse alan (yer) kalır, yalnız bağı kopar.
 * - Poligonun içine düşen envanter taşınmazı alana bağlanır (tasinmaz_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kira_alanlari', function (Blueprint $table) {
            $table->dropForeign(['kira_dosya_id']);
        });

        Schema::table('kira_alanlari', function (Blueprint $table) {
            $table->unsignedBigInteger('kira_dosya_id')->nullable()->change();
            $table->foreign('kira_dosya_id')->references('id')->on('kira_dosyalari')->nullOnDelete();

            $table->foreignId('tasinmaz_id')->nullable()->after('poligon_id')
                ->constrained('tasinmazlar')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kira_alanlari', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tasinmaz_id');
            $table->dropForeign(['kira_dosya_id']);
        });

        Schema::table('kira_alanlari', function (Blueprint $table) {
            // Dosyasız alanlar eski yapıya sığmaz; geri alırken önce bunlar silinmeli
            $table->unsignedBigInteger('kira_dosya_id')->nullable(false)->change();
            $table->foreign('kira_dosya_id')->references('id')->on('kira_dosyalari')->cascadeOnDelete();
        });
    }
};

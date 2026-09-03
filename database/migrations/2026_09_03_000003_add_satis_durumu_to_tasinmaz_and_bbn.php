<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->string('satis_durumu', 20)->default('envanterde')->after('kira_var');
            $table->index('satis_durumu');
        });

        Schema::table('bagimsiz_bolumler', function (Blueprint $table) {
            $table->string('satis_durumu', 20)->default('envanterde')->after('meclis_satis_karari_var');
            $table->boolean('tahsis_var')->default(false)->after('satis_durumu');
            $table->boolean('ust_hakki_var')->default(false)->after('tahsis_var');
            $table->boolean('kira_var')->default(false)->after('ust_hakki_var');
            $table->index('satis_durumu');
        });
    }

    public function down(): void
    {
        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->dropIndex(['satis_durumu']);
            $table->dropColumn('satis_durumu');
        });

        Schema::table('bagimsiz_bolumler', function (Blueprint $table) {
            $table->dropIndex(['satis_durumu']);
            $table->dropColumn(['satis_durumu', 'tahsis_var', 'ust_hakki_var', 'kira_var']);
        });
    }
};

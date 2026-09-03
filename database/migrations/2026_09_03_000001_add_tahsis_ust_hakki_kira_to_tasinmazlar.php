<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->boolean('tahsis_var')->default(false)->after('meclis_satis_karari_var');
            $table->boolean('ust_hakki_var')->default(false)->after('tahsis_var');
            $table->boolean('kira_var')->default(false)->after('ust_hakki_var');

            $table->index('tahsis_var');
            $table->index('ust_hakki_var');
            $table->index('kira_var');
        });
    }

    public function down(): void
    {
        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->dropIndex(['tahsis_var']);
            $table->dropIndex(['ust_hakki_var']);
            $table->dropIndex(['kira_var']);

            $table->dropColumn(['tahsis_var', 'ust_hakki_var', 'kira_var']);
        });
    }
};

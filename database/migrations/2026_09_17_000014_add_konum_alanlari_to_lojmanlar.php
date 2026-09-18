<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lojmanlar', function (Blueprint $table) {
            $table->foreignId('il_id')->nullable()->after('mudurluk_id')->constrained('iller')->nullOnDelete();
            $table->foreignId('ilce_id')->nullable()->after('il_id')->constrained('ilceler')->nullOnDelete();
            $table->foreignId('mahalle_id')->nullable()->after('ilce_id')->constrained('mahalleler')->nullOnDelete();
            $table->string('ada', 20)->nullable()->after('mahalle_id');
            $table->string('parsel', 20)->nullable()->after('ada');
            $table->index(['mahalle_id', 'ada', 'parsel']);
        });
    }

    public function down(): void
    {
        Schema::table('lojmanlar', function (Blueprint $table) {
            $table->dropForeign(['il_id']);
            $table->dropForeign(['ilce_id']);
            $table->dropForeign(['mahalle_id']);
            $table->dropIndex(['mahalle_id', 'ada', 'parsel']);
            $table->dropColumn(['il_id', 'ilce_id', 'mahalle_id', 'ada', 'parsel']);
        });
    }
};

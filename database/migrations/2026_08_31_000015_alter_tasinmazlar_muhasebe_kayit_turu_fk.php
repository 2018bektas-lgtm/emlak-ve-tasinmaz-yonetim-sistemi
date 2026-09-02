<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->dropIndex(['muhasebe_niteligi']);
            $table->dropIndex(['kayit_turu']);
            $table->dropColumn(['muhasebe_niteligi', 'kayit_turu']);
        });

        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->foreignId('muhasebe_kayit_id')->nullable()->after('nitelik')
                ->constrained('muhasebe_kayitlari')->restrictOnDelete();
            $table->foreignId('kayit_turu_id')->nullable()->after('muhasebe_kayit_id')
                ->constrained('kayit_turleri')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->dropConstrainedForeignId('muhasebe_kayit_id');
            $table->dropConstrainedForeignId('kayit_turu_id');
        });

        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->string('muhasebe_niteligi', 100)->nullable()->after('nitelik');
            $table->string('kayit_turu', 50)->nullable()->after('muhasebe_niteligi');
            $table->index('muhasebe_niteligi');
            $table->index('kayit_turu');
        });
    }
};

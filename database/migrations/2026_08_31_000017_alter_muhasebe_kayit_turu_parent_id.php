<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // muhasebe_kayitlari — hiyerarsik (Turkiye devlet muhasebe hesap plani)
        Schema::table('muhasebe_kayitlari', function (Blueprint $table) {
            $table->dropUnique(['ad']);
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('muhasebe_kayitlari')->cascadeOnDelete();
            $table->index(['parent_id', 'sira']);
        });

        // kayit_turleri — hiyerarsik (1.1.1 gibi numaralı)
        Schema::table('kayit_turleri', function (Blueprint $table) {
            $table->dropUnique(['ad']);
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('kayit_turleri')->cascadeOnDelete();
            $table->index(['parent_id', 'sira']);
        });
    }

    public function down(): void
    {
        Schema::table('muhasebe_kayitlari', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'sira']);
            $table->dropConstrainedForeignId('parent_id');
            $table->unique('ad');
        });

        Schema::table('kayit_turleri', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'sira']);
            $table->dropConstrainedForeignId('parent_id');
            $table->unique('ad');
        });
    }
};

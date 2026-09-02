<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->string('muhasebe_niteligi', 100)->nullable()->after('nitelik');
            $table->string('kayit_turu', 50)->nullable()->after('muhasebe_niteligi');
            $table->string('mevcut_kullanim_sekli', 100)->nullable()->after('kayit_turu');
            $table->string('isgal_durumu', 20)->nullable()->after('mevcut_kullanim_sekli');
            $table->boolean('uzeri_bina_var_mi')->default(false)->after('isgal_durumu');
            $table->boolean('meclis_satis_karari_var')->default(false)->after('uzeri_bina_var_mi');
            $table->text('aciklama')->nullable()->after('meclis_satis_karari_var');

            $table->index('muhasebe_niteligi');
            $table->index('kayit_turu');
            $table->index('meclis_satis_karari_var');

            $table->dropIndex(['nitelik']);
            $table->index('nitelik');
        });
    }

    public function down(): void
    {
        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->dropIndex(['muhasebe_niteligi']);
            $table->dropIndex(['kayit_turu']);
            $table->dropIndex(['meclis_satis_karari_var']);

            $table->dropColumn([
                'muhasebe_niteligi',
                'kayit_turu',
                'mevcut_kullanim_sekli',
                'isgal_durumu',
                'uzeri_bina_var_mi',
                'meclis_satis_karari_var',
                'aciklama',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasinmaz_hisseler', function (Blueprint $table) {
            // TKGM tarafında hisse pay/payda kesirli olabilir (ör. 12,5), bu yüzden decimal.
            // Kullanıcı bunları girer; hisse_yuzolcum backend'de pay/payda*tasinmaz.alan
            // formülüyle otomatik hesaplanır ve DB'de saklanır (raporlama için).
            $table->decimal('hisse_pay', 20, 4)->nullable()->after('hisse_no');
            $table->decimal('hisse_payda', 20, 4)->nullable()->after('hisse_pay');
        });
    }

    public function down(): void
    {
        Schema::table('tasinmaz_hisseler', function (Blueprint $table) {
            $table->dropColumn(['hisse_pay', 'hisse_payda']);
        });
    }
};

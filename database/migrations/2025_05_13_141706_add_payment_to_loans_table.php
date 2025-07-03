<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('loans', function (Blueprint $table) {
        // Adjust precision/scale if you like
        $table->decimal('payment', 12, 2)->nullable()->after('crb_fee');
    });
}

public function down(): void
{
    Schema::table('loans', function (Blueprint $table) {
        $table->dropColumn('payment');
    });
}
};

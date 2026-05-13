<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missed_installments', function (Blueprint $table) {
            $table->date('missed_date')->nullable()->after('due_month');
            $table->index('missed_date');
        });
    }

    public function down(): void
    {
        Schema::table('missed_installments', function (Blueprint $table) {
            $table->dropIndex(['missed_date']);
            $table->dropColumn('missed_date');
        });
    }
};

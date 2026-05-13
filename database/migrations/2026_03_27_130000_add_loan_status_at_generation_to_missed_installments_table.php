<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missed_installments', function (Blueprint $table) {
            $table->string('loan_status_at_generation')->nullable()->after('missed_date');
            $table->index('loan_status_at_generation');
        });
    }

    public function down(): void
    {
        Schema::table('missed_installments', function (Blueprint $table) {
            $table->dropIndex(['loan_status_at_generation']);
            $table->dropColumn('loan_status_at_generation');
        });
    }
};

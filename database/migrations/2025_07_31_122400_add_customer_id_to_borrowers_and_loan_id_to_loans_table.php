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
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('customer_id')->nullable();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->string('loan_id', 20);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('loan_id');
        });
    }
};

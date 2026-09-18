<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('loans', function (Blueprint $table) {
        $table->decimal('monthly_insurance', 15, 2)->default(0);
        $table->decimal('total_monthly_repayment', 15, 2)->default(0);
    });
}

public function down()
{
    Schema::table('loans', function (Blueprint $table) {
        $table->dropColumn(['monthly_insurance', 'total_monthly_repayment']);
    });
}

};

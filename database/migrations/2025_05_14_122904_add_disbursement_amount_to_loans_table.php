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
        $table->decimal('disbursement_amount', 15,  2)
              ->default(0)
              ->after('total_monthly_repayment');
    });
}

public function down()
{
    Schema::table('loans', function (Blueprint $table) {
        $table->dropColumn('disbursement_amount');
    });
}

};

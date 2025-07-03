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
        $table->decimal('admin_fee', 10, 2)->nullable();
        $table->decimal('insurance_fee', 10, 2)->nullable();
        $table->decimal('arrangement_fee', 10, 2)->nullable();
        $table->decimal('crb_fee', 10, 2)->nullable();
    });
}

public function down()
{
    Schema::table('loans', function (Blueprint $table) {
        $table->dropColumn(['admin_fee', 'insurance_fee', 'arrangement_fee', 'crb_fee']);
    });
}

};

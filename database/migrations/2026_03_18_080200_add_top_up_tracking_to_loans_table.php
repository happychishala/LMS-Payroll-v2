<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('top_up_amount', 15, 2)->nullable();
            $table->decimal('top_up_source_total', 15, 2)->nullable();
            $table->string('top_up_parent_loan_id')->nullable();
            $table->string('top_up_child_loan_id')->nullable();
            $table->date('top_up_settled_at')->nullable();
            $table->string('top_up_batch_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'top_up_amount',
                'top_up_source_total',
                'top_up_parent_loan_id',
                'top_up_child_loan_id',
                'top_up_settled_at',
                'top_up_batch_reference',
            ]);
        });
    }
};

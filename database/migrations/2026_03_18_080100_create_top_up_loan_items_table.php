<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('top_up_loan_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('top_up_loan_batch_id');
            $table->string('old_loan_id');
            $table->string('old_loan_number')->nullable();
            $table->decimal('old_balance', 15, 2)->default(0);
            $table->unsignedBigInteger('settlement_repayment_id')->nullable();
            $table->string('old_status_before')->nullable();
            $table->string('old_status_after')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('top_up_loan_batch_id')->references('id')->on('top_up_loan_batches')->cascadeOnDelete();
            $table->foreign('old_loan_id')->references('loan_id')->on('loans')->cascadeOnDelete();
            $table->foreign('settlement_repayment_id')->references('id')->on('repayments')->nullOnDelete();
            $table->unique(['top_up_loan_batch_id', 'old_loan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_up_loan_items');
    }
};

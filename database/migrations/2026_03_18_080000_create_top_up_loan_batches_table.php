<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('top_up_loan_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_reference')->unique();
            $table->unsignedBigInteger('borrower_id');
            $table->string('new_loan_id');
            $table->decimal('topup_amount', 15, 2)->default(0);
            $table->decimal('total_settled_amount', 15, 2)->default(0);
            $table->decimal('new_principal_amount', 15, 2)->default(0);
            $table->unsignedInteger('source_loan_count')->default(0);
            $table->date('loan_release_date');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('borrower_id')->references('id')->on('borrowers')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('new_loan_id')->references('loan_id')->on('loans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_up_loan_batches');
    }
};

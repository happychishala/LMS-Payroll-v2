<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skipped_repayments', function (Blueprint $table) {
            $table->id();
            $table->string('csv_file')->nullable();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('import_type')->nullable();
            $table->text('reason');
            $table->string('loan_id')->nullable();
            $table->string('loan_number')->nullable();
            $table->string('employee_no')->nullable();
            $table->string('batch_no')->nullable();
            $table->integer('repayment_number')->nullable();
            $table->date('receipt_date')->nullable();
            $table->decimal('receipt_amount', 15, 2)->nullable();
            $table->string('reference_number')->nullable();
            $table->unsignedBigInteger('matched_repayment_id')->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamps();

            $table->index(['import_type', 'created_at']);
            $table->index(['csv_file', 'row_number']);
            $table->index(['loan_id', 'receipt_date']);
            $table->index('employee_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skipped_repayments');
    }
};

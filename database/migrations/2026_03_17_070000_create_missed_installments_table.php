<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missed_installments', function (Blueprint $table) {
            $table->id();
            $table->string('loan_id');
            $table->string('employee_no')->nullable();
            $table->string('employer')->nullable();
            $table->date('due_month');
            $table->decimal('expected_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('shortfall_amount', 15, 2)->default(0);
            $table->unsignedInteger('repayment_count')->default(0);
            $table->string('status')->default('missed');
            $table->text('remarks')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'due_month']);
            $table->index(['due_month', 'status']);
            $table->index('employee_no');
            $table->index('employer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missed_installments');
    }
};

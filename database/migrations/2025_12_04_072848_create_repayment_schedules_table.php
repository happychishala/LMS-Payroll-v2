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
        Schema::create('repayment_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('loan_id'); // Foreign key to loans
            $table->integer('repayment_number');
            $table->date('due_date');
            $table->decimal('monthly_payment', 12, 2);
            $table->decimal('principal_payment', 12, 2)->nullable();
            $table->decimal('interest_payment', 12, 2)->nullable();
            $table->decimal('insurance_payment', 12, 2)->nullable();
            $table->decimal('opening_balance', 12, 2)->nullable();
            $table->decimal('closing_balance', 12, 2)->nullable();
            $table->enum('payment_status', ['Unpaid', 'Paid', 'Partial'])->default('Unpaid');
            $table->timestamps();

            // Foreign key
            $table->foreign('loan_id')
                ->references('loan_id')
                ->on('loans')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repayment_schedules');
    }
};

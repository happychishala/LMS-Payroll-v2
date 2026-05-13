<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('withholdings', function (Blueprint $table) {
            $table->id();
            $table->string('loan_id');
            $table->string('borrower_id');
            $table->date('installment_due_date');
            $table->decimal('installment_amount', 12, 2);
            $table->enum('withholding_status', ['Pending', 'Refund', 'Withheld', 'Refunded'])->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('loan_id')->references('loan_id')->on('loans')->onDelete('cascade');
            $table->foreign('borrower_id')->references('customer_id')->on('borrowers')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('withholdings');
    }
};

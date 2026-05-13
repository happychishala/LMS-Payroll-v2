<?php
// ...new file...
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvalidRepaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('invalid_repayments', function (Blueprint $table) {
            $table->id();
            $table->string('csv_file')->nullable();
            $table->integer('row_number')->nullable();
            $table->string('employee_no')->nullable();
            $table->string('name')->nullable();
            $table->string('nrc')->nullable();
            $table->string('amount_raw')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('receipt_date_raw')->nullable();
            $table->date('receipt_date')->nullable();
            $table->text('errors')->nullable();
            $table->enum('status', ['pending','processed','ignored'])->default('pending');
            $table->unsignedBigInteger('processed_rep_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('employee_no');
            $table->index(['status', 'row_number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('invalid_repayments');
    }
}
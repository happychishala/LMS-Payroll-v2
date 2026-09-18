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
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('other_names')->nullable();
            $table->integer('age')->nullable();
            $table->string('title')->nullable();
            $table->date('retirement_date')->nullable();
            $table->date('term_date')->nullable();
            $table->date('start_contract')->nullable();
            $table->date('end_contract')->nullable();
            $table->string('contract_type')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('nationality')->nullable();
            $table->string('employer_number')->nullable();
            $table->string('employer_address')->nullable();
            $table->string('employer_position')->nullable();
            $table->string('cycle')->nullable();
            $table->string('next_of_kin_nrc')->nullable();
            $table->string('bank_account_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn([
                'other_names',
                'age',
                'title',
                'retirement_date',
                'term_date',
                'start_contract',
                'end_contract',
                'contract_type',
                'marital_status',
                'nationality',
                'employer_number',
                'employer_address',
                'employer_position',
                'cycle',
                'next_of_kin_nrc',
                'bank_account_type',
            ]);
        });
    }
};

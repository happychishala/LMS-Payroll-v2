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
            $table->string('other_names')->nullable()->after('last_name');
            $table->integer('age')->nullable()->after('dob');
            $table->string('title')->nullable()->after('mine_number');
            $table->date('retirement_date')->nullable()->after('title');
            $table->date('term_date')->nullable()->after('retirement_date');
            $table->date('start_contract')->nullable()->after('term_date');
            $table->date('end_contract')->nullable()->after('start_contract');
            $table->string('contract_type')->nullable()->after('end_contract');
            $table->string('marital_status')->nullable()->after('contract_type');
            $table->string('nationality')->nullable()->after('marital_status');
            $table->string('employer_number')->nullable()->after('employer');
            $table->string('employer_address')->nullable()->after('employer_number');
            $table->string('employer_position')->nullable()->after('employer_address');
            $table->string('cycle')->nullable()->after('employer_position');
            $table->string('next_of_kin_nrc')->nullable()->after('relationship_next_of_kin');
            $table->string('bank_account_type')->nullable()->after('bank_account_number');
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

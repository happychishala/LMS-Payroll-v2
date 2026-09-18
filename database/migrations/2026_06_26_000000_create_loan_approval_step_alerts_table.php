<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_approval_step_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('status')->unique();
            $table->string('label');
            $table->json('role_names')->nullable();
            $table->json('emails')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('loan_approval_step_alerts')->insert([
            [
                'status' => 'requested',
                'label' => 'Loan Request Review',
                'role_names' => json_encode(['Loan Officer', 'Loans Officer', 'Credit Officer']),
                'emails' => json_encode([]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'status' => 'processing',
                'label' => 'Loan Approval Review',
                'role_names' => json_encode(['Loan Manager', 'Credit Manager', 'Manager']),
                'emails' => json_encode([]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'status' => 'approved',
                'label' => 'Approved Loan Disbursement Notice',
                'role_names' => json_encode(['Accountant', 'Finance', 'Finance Officer']),
                'emails' => json_encode([]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_approval_step_alerts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skipped_repayments', function (Blueprint $table) {
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('posted_repayment_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('post_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('skipped_repayments', function (Blueprint $table) {
            $table->dropColumn(['status', 'posted_repayment_id', 'posted_at', 'post_error']);
        });
    }
};

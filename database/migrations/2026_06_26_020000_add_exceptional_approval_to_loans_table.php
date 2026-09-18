<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'exceptional_approval')) {
                $table->boolean('exceptional_approval')->default(false)->after('loan_status');
            }

            if (! Schema::hasColumn('loans', 'exceptional_approval_email_screenshot_path')) {
                $table->string('exceptional_approval_email_screenshot_path')->nullable()->after('exceptional_approval');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'exceptional_approval_email_screenshot_path')) {
                $table->dropColumn('exceptional_approval_email_screenshot_path');
            }

            if (Schema::hasColumn('loans', 'exceptional_approval')) {
                $table->dropColumn('exceptional_approval');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('status_reasons', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();         // e.g. OFF01
            $t->string('group')->nullable();      // e.g. Off-Payroll, Write-Off, Settlement
            $t->string('label');                  // e.g. "Stopped at payroll – employer transfer"
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // Link to loans (adjust if you prefer linking to borrowers)
        Schema::table('loans', function (Blueprint $t) {
            $t->foreignId('status_reason_id')->nullable()->constrained('status_reasons')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('loans', fn (Blueprint $t) => $t->dropConstrainedForeignId('status_reason_id'));
        Schema::dropIfExists('status_reasons');
    }
};

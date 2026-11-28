<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Duplicate override (Michael's answer to Q23, 2026-11-28; PLAN 2.4, D113-D115): an admin lets one blocked unit go on
| although another active unit holds the same contract key. One row per blocked unit and the unit it matched; the
| override excuses that pair only. Who and when are kept here and in the activity log.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->nullable()->constrained('duplicate_attempts')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('matched_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('reason', 500);
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['unit_id', 'matched_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_overrides');
    }
};

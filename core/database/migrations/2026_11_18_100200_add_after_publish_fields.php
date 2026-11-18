<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Phase 4, after publishing (PLAN 2.2.7, 2.2.8):
| - recheck_after_edit_at: a published unit's photos or description changed (free tier); the admin's "check after
|   edit" list reads it and clears it.
| - withdraw_requested_at: the seller asked to withdraw a reserved unit (an open deal); the admin handles it.
| - sold_at: when the unit was sold; with withdrawn_at and rejected_at it starts the national ID retention clock.
| All private (Unit::PRIVATE).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->timestamp('recheck_after_edit_at')->nullable()->after('withdraw_reason');
            $table->timestamp('withdraw_requested_at')->nullable()->after('recheck_after_edit_at');
            $table->timestamp('sold_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['recheck_after_edit_at', 'withdraw_requested_at', 'sold_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Part 7.4 (G4, Michael 2026-11-27): an admin may suspend a seller or buyer, with a reason; it blocks sign-in and takes
| the person's units off the site, logged and reversible.
| - users.suspended_at / suspended_reason / suspended_by: who and why (never shown on the public site).
| - units.held_at: the unit is held off the site while its seller is suspended; its state is kept, so lifting the
|   suspension puts it back exactly as it was. Every public query reads it (Unit::scopePublic, Unit::scopeListed).
| - unit_requests.assigned_to / staff_note: the request inbox (PLAN 2.4: assign, note, close); never shown to the buyer.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable()->after('email_verified_at');
            $table->string('suspended_reason', 500)->nullable()->after('suspended_at');
            $table->foreignId('suspended_by')->nullable()->after('suspended_reason')->constrained('admin_users')->nullOnDelete();
        });
        Schema::table('units', function (Blueprint $table): void {
            $table->timestamp('held_at')->nullable()->after('sold_at')->comment('held off the site while the seller is suspended');
        });
        Schema::table('unit_requests', function (Blueprint $table): void {
            $table->foreignId('assigned_to')->nullable()->after('state')->constrained('admin_users')->nullOnDelete();
            $table->text('staff_note')->nullable()->after('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('unit_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn('staff_note');
        });
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('held_at');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspended_at', 'suspended_reason']);
        });
    }
};

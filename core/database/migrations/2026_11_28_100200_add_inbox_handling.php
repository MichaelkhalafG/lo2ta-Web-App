<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Part 7.6 (G5, Michael 2026-11-27): the contact and partnership inboxes. A message is marked handled (who, when, a team
| note); handled messages are deleted after `handled_message_retention_months` (12, a setting) by `messages:purge`.
*/

return new class extends Migration
{
    public function up(): void
    {
        foreach (['contact_messages', 'partnership_requests'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->timestamp('handled_at')->nullable()->after('state')->index();
                $table->foreignId('handled_by')->nullable()->after('handled_at')->constrained('admin_users')->nullOnDelete();
                $table->text('staff_note')->nullable()->after('handled_by');
            });
        }
    }

    public function down(): void
    {
        foreach (['contact_messages', 'partnership_requests'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('handled_by');
                $table->dropColumn(['handled_at', 'staff_note']);
            });
        }
    }
};

<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use App\Models\PartnershipRequest;
use App\Support\Activity\ActivityLogger;
use App\Support\Settings\Settings;
use Illuminate\Console\Command;

/**
 * G5 (Michael 2026-11-27): contact and partnership messages marked handled are deleted after
 * `handled_message_retention_months` (12). Messages never handled are kept. One log line per run with the counts
 * (never the contents). --dry-run counts only and deletes nothing.
 */
class PurgeHandledMessages extends Command
{
    protected $signature = 'messages:purge {--dry-run : count only, delete nothing}';

    protected $description = 'Delete handled contact and partnership messages past their retention period (G5)';

    public function handle(Settings $settings, ActivityLogger $log): int
    {
        $months = $settings->int('handled_message_retention_months');
        $before = now()->subMonths($months);
        $contact = ContactMessage::query()->where('state', 'handled')->whereNotNull('handled_at')->where('handled_at', '<', $before);
        $partner = PartnershipRequest::query()->where('state', 'handled')->whereNotNull('handled_at')->where('handled_at', '<', $before);
        $counts = ['contact' => (clone $contact)->count(), 'partnership' => (clone $partner)->count()];

        if ($this->option('dry-run')) {
            $this->line("would delete {$counts['contact']} contact and {$counts['partnership']} partnership messages handled before {$before->toDateString()} ({$months} months)");

            return self::SUCCESS;
        }
        $contact->delete();
        $partner->delete();
        if ($counts['contact'] + $counts['partnership'] > 0) {
            $log->record('messages.purged', null, null, [...$counts, 'months' => $months]);
        }
        $this->line("deleted {$counts['contact']} contact and {$counts['partnership']} partnership messages");

        return self::SUCCESS;
    }
}

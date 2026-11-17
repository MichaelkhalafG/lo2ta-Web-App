<?php

namespace App\Console\Commands;

use App\Support\Mail\Outbox;
use Illuminate\Console\Command;

class OutboxSend extends Command
{
    protected $signature = 'outbox:send {--limit=50 : Rows per run}';

    protected $description = 'Send mail_outbox rows that are due (scheduled every minute)';

    public function handle(Outbox $outbox): int
    {
        $limit = $this->option('limit');
        $result = $outbox->sendDue(is_numeric($limit) ? (int) $limit : 50);

        $this->line("outbox: sent {$result['sent']}, failed {$result['failed']}");

        return self::SUCCESS;
    }
}

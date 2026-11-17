<?php

namespace App\Console\Commands;

use App\Enums\MailState;
use App\Support\Mail\Outbox;
use Illuminate\Console\Command;

class MailTest extends Command
{
    protected $signature = 'mail:test {email : Recipient} {--locale=ar : ar or en}';

    protected $description = 'Send one test mail through the outbox, to prove the mailer';

    public function handle(Outbox $outbox): int
    {
        $email = $this->argument('email');
        $locale = $this->option('locale');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('STOP - not an e-mail address');

            return self::FAILURE;
        }
        if (! in_array($locale, ['ar', 'en'], true)) {
            $this->error('STOP - locale must be ar or en');

            return self::FAILURE;
        }

        $row = $outbox->send($email, null, $locale, 'system.test', [
            'sent_at' => now('Africa/Cairo')->format('Y-m-d H:i'),
        ]);

        if ($row->state === MailState::Sent) {
            $this->info("SENT - outbox row {$row->id}");

            return self::SUCCESS;
        }

        $this->error("NOT SENT - outbox row {$row->id} is {$row->state->value}: {$row->last_error}");

        return self::FAILURE;
    }
}

<?php

namespace App\Support\Mail;

use Illuminate\Mail\Mailable;

/**
 * A mail the outbox can rebuild from its stored payload, so a failed send can be retried later by the scheduler.
 * Register each template code in config/mail_templates.php.
 */
interface OutboxTemplate
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload, string $locale): Mailable;
}

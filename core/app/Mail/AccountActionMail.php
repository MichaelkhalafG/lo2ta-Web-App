<?php

namespace App\Mail;

use App\Support\Mail\OutboxTemplate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An account e-mail with one button: verify the e-mail address or reset the password (PLAN 2.1). The template code
 * (account.verify_email / account.reset_password / account.login_locked) picks the wording in lang/{locale}/mail.php.
 */
class AccountActionMail extends Mailable implements OutboxTemplate
{
    public function __construct(
        public readonly string $kind,
        public readonly string $name,
        public readonly string $url,
    ) {}

    public static function fromPayload(array $payload, string $locale): Mailable
    {
        $kind = $payload['kind'] ?? '';
        $name = $payload['name'] ?? '';
        $url = $payload['url'] ?? '';

        return new self(
            in_array($kind, ['verify_email', 'reset_password', 'login_locked'], true) ? $kind : 'verify_email',
            is_string($name) ? $name : '',
            is_string($url) ? $url : '',
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::line("mail.{$this->kind}.subject"));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.account-action', with: [
            'heading' => self::line("mail.{$this->kind}.subject"),
            'body' => self::line("mail.{$this->kind}.body"),
            'button' => self::line("mail.{$this->kind}.button"),
            'note' => self::line("mail.{$this->kind}.note"),
        ]);
    }

    private static function line(string $key): string
    {
        $value = __($key);

        return is_string($value) ? $value : $key;
    }
}

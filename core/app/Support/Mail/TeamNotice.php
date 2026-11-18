<?php

namespace App\Support\Mail;

use App\Models\AdminUser;
use App\Support\Settings\Settings;

/**
 * Notices for the team (a new budget, later a new unit or request). They go to the official team mailbox in the
 * setting `team_email` (Michael 2026-11-18); while that is empty, to every active admin account (RUN D14).
 */
final class TeamNotice
{
    public function __construct(private readonly Outbox $outbox, private readonly Settings $settings) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return int how many e-mails were queued
     */
    public function send(string $template, array $payload): int
    {
        $mailbox = $this->settings->string('team_email');
        if ($mailbox !== '') {
            $this->outbox->send($mailbox, null, 'ar', $template, $payload);

            return 1;
        }

        $sent = 0;
        foreach (AdminUser::query()->where('is_active', true)->get() as $admin) {
            $locale = in_array($admin->locale, ['ar', 'en'], true) ? $admin->locale : 'ar';
            $this->outbox->send($admin->email, $admin->name, $locale, $template, $payload);
            $sent++;
        }

        return $sent;
    }
}

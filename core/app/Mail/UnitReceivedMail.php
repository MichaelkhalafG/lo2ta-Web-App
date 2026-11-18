<?php

namespace App\Mail;

use App\Models\ExitReport;
use App\Support\Mail\OutboxTemplate;
use App\Support\Uploads\UploadService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To the seller after submit (PLAN 2.2.2 "after submit"): received, the reference, the 48-hour review promise and
 * the exit report PDF "as declared" in the seller's language. Placeholder wording (PLAN 6 row 23).
 */
class UnitReceivedMail extends Mailable implements OutboxTemplate
{
    public function __construct(public readonly string $reference, public readonly ?string $reportPath = null) {}

    public static function fromPayload(array $payload, string $locale): Mailable
    {
        $reference = $payload['reference'] ?? '';
        $reportId = $payload['exit_report_id'] ?? null;
        $path = is_int($reportId) ? ExitReport::query()->whereKey($reportId)->value('pdf_path') : null;

        return new self(is_scalar($reference) ? (string) $reference : '', is_string($path) ? $path : null);
    }

    public function envelope(): Envelope
    {
        $subject = __('mail.unit_received.subject', ['reference' => $this->reference]);

        return new Envelope(subject: is_string($subject) ? $subject : 'mail.unit_received.subject');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.seller-unit-received', with: ['reference' => $this->reference]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if ($this->reportPath === null) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(UploadService::PRIVATE_DISK, $this->reportPath)
                ->as('exit-report-'.ltrim($this->reference, '#').'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}

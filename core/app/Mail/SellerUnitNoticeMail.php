<?php

namespace App\Mail;

use App\Enums\DocumentType;
use App\Models\ExitReport;
use App\Support\Mail\OutboxTemplate;
use App\Support\Review\FieldDisplay;
use App\Support\Uploads\UploadService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notices to the seller about their unit (PLAN 2.2.7): withdrawn, and the review outcomes of part 7.2 (changes
 * requested with the notes per field and document, rejected with the reason, published with the "after review" exit
 * report attached (G19), verified, a withdrawal request declined). Never a staff name. Our wording, for the client to
 * review (G20).
 */
class SellerUnitNoticeMail extends Mailable implements OutboxTemplate
{
    public const KINDS = ['withdrawn', 'changes_requested', 'rejected', 'published', 'published_verified', 'verified', 'withdraw_declined', 'change_rejected'];

    /**
     * @param  list<array{label: string, note: string}>  $fields
     * @param  list<array{label: string, note: string}>  $documents
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $kind,
        public readonly ?string $reason = null,
        public readonly array $fields = [],
        public readonly array $documents = [],
        public readonly ?string $reportPath = null,
        public readonly ?string $accountUrl = null,
        public readonly ?string $unitUrl = null,
    ) {}

    public static function fromPayload(array $payload, string $locale): Mailable
    {
        $text = static fn (string $key): ?string => isset($payload[$key]) && is_string($payload[$key]) && $payload[$key] !== '' ? $payload[$key] : null;
        $kind = $text('kind') ?? 'withdrawn';
        $fields = [];
        foreach (is_array($payload['fields'] ?? null) ? $payload['fields'] : [] as $row) {
            if (is_array($row) && is_string($row['field'] ?? null) && is_string($row['note'] ?? null)) {
                $fields[] = ['label' => FieldDisplay::label($row['field'], $locale), 'note' => $row['note']];
            }
        }
        $documents = [];
        foreach (is_array($payload['documents'] ?? null) ? $payload['documents'] : [] as $row) {
            if (! is_array($row) || ! is_string($row['type'] ?? null)) {
                continue;
            }
            $type = DocumentType::tryFrom($row['type']);
            if ($type !== null) {
                $documents[] = ['label' => $type->label($locale), 'note' => is_string($row['note'] ?? null) ? $row['note'] : ''];
            }
        }
        $reportId = $payload['exit_report_id'] ?? null;
        $path = is_int($reportId) ? ExitReport::query()->whereKey($reportId)->value('pdf_path') : null;
        $reference = $payload['reference'] ?? '';

        return new self(
            is_scalar($reference) ? (string) $reference : '',
            in_array($kind, self::KINDS, true) ? $kind : 'withdrawn',
            $text('reason'),
            $fields,
            $documents,
            is_string($path) ? $path : null,
            $text('account_url'),
            $text('unit_url'),
        );
    }

    public function envelope(): Envelope
    {
        $subject = __("mail.unit_notice.{$this->kind}.subject", ['reference' => $this->reference]);

        return new Envelope(subject: is_string($subject) ? $subject : "mail.unit_notice.{$this->kind}.subject");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.seller-unit-notice', with: [
            'reference' => $this->reference,
            'kind' => $this->kind,
            'reason' => $this->reason,
            'fields' => $this->fields,
            'documents' => $this->documents,
            'accountUrl' => $this->accountUrl,
            'unitUrl' => $this->unitUrl,
        ]);
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
                ->as('exit-report-'.strtolower(ltrim($this->reference, '#')).'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}

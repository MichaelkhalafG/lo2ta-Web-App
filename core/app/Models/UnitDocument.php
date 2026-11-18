<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * PRIVATE in full: files on the private disk under a random name, served only to the unit's seller and to staff
 * through authorised routes (Michael 2026-11-18, PLAN 2.2.15). The uploaded name is kept for display only.
 */
class UnitDocument extends Model
{
    protected $guarded = ['id'];

    /** National ID front and back are one file each (PLAN 2.2.14). */
    public const SINGLE = [DocumentType::NationalIdFront, DocumentType::NationalIdBack];

    protected static function booted(): void
    {
        static::creating(static function (UnitDocument $document): void {
            $document->public_id ??= (string) Str::ulid();
        });
    }

    /** The name a download carries: generated, never the uploaded file name. */
    public function downloadName(): string
    {
        $ext = match ($this->mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };

        return $this->type->value.'-'.strtolower(substr((string) $this->public_id, -6)).'.'.$ext;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'bytes' => 'integer',
            'flags' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'purge_after' => 'immutable_datetime',
            'purged_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}

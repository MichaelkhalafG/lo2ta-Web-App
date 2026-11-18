<?php

namespace App\Console\Commands;

use App\Enums\DocumentType;
use App\Enums\UnitState;
use App\Models\Unit;
use App\Models\UnitDocument;
use App\Support\Activity\ActivityLogger;
use App\Support\Settings\Settings;
use App\Support\Uploads\UploadService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * National ID retention (PLAN 2.2.8, option A, approved 2026-11-17). Daily:
 * - a unit sold, withdrawn or rejected loses its ID copies `id_retention_days` (90) after that date;
 * - a draft never submitted loses them `draft_id_retention_days` (180) after its last activity (the unit's last
 *   change or its last upload, whichever is later).
 * The file is deleted; the row stays (type, sha256, review, purged_at) as the record. Every purge is logged.
 * --dry-run lists what would go and changes nothing.
 */
class PurgeIdCopies extends Command
{
    protected $signature = 'ids:purge {--dry-run : list only, delete nothing}';

    protected $description = 'Delete national ID copies past their retention period (PLAN 2.2.8)';

    public function handle(Settings $settings, ActivityLogger $log): int
    {
        $now = CarbonImmutable::now();
        $closedBefore = $now->subDays($settings->int('id_retention_days'));
        $idleBefore = $now->subDays($settings->int('draft_id_retention_days'));
        $types = [DocumentType::NationalIdFront->value, DocumentType::NationalIdBack->value];

        $due = UnitDocument::query()
            ->whereIn('type', $types)
            ->whereNotNull('path')
            ->with('unit')
            ->get()
            ->filter(function (UnitDocument $document) use ($closedBefore, $idleBefore): bool {
                $unit = $document->unit;
                if (! $unit instanceof Unit) {
                    return false;
                }
                $closedAt = match ($unit->state) {
                    UnitState::Sold => $unit->sold_at,
                    UnitState::Withdrawn => $unit->withdrawn_at,
                    UnitState::Rejected => $unit->rejected_at,
                    default => null,
                };
                if ($closedAt !== null) {
                    return $closedAt->lt($closedBefore);
                }
                if ($unit->state === UnitState::Draft && $unit->serial === null) {
                    return self::lastActivity($unit)->lt($idleBefore);
                }

                return false;
            });

        foreach ($due as $document) {
            $this->line(($this->option('dry-run') ? 'WOULD PURGE ' : 'PURGED ').$document->type->value.' of unit '.$document->unit_id);
            if ($this->option('dry-run')) {
                continue;
            }
            Storage::disk(UploadService::PRIVATE_DISK)->delete((string) $document->path);
            $document->forceFill(['path' => null, 'purged_at' => $now])->save();
            $log->record('document.purged', $document->unit, null, ['type' => $document->type->value, 'rule' => 'PLAN 2.2.8']);
        }
        $this->info(($this->option('dry-run') ? 'would purge ' : 'purged ').$due->count().' ID copies');

        return self::SUCCESS;
    }

    private static function lastActivity(Unit $unit): CarbonImmutable
    {
        $upload = $unit->documents()->max('created_at');
        $changed = CarbonImmutable::parse($unit->updated_at ?? $unit->created_at ?? 'now');

        return is_string($upload) && CarbonImmutable::parse($upload)->gt($changed) ? CarbonImmutable::parse($upload) : $changed;
    }
}

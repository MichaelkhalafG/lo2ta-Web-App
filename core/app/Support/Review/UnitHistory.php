<?php

namespace App\Support\Review;

use App\Enums\DocumentType;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\Unit;
use App\Support\Dashboard\Text;

/**
 * A unit's history for the review screen, read as a drawing's revision block (PLAN 2.7): every named log line about
 * the unit, newest first, in words, with who did it and the reason. Times in Cairo time.
 */
final class UnitHistory
{
    /**
     * @return list<array{at: string, event: string, by: string, note: string|null}>
     */
    public static function rows(Unit $unit, int $limit = 80): array
    {
        $lines = ActivityLog::query()->where('subject_type', 'units')->where('subject_id', $unit->id)->latest('id')->limit($limit)->get();
        $admins = AdminUser::query()->whereKey($lines->where('actor_type', 'admin')->pluck('actor_id')->filter()->unique()->all())->pluck('name', 'id');

        $rows = [];
        foreach ($lines as $line) {
            $by = match ($line->actor_type) {
                'admin' => is_string($admins->get($line->actor_id)) ? (string) $admins->get($line->actor_id) : '#'.$line->actor_id,
                'user' => $line->actor_id === $unit->seller_id ? Text::get('units.by_seller') : Text::get('units.by_user'),
                default => Text::get('staff.by_system'),
            };
            $rows[] = [
                'at' => $line->created_at->timezone('Africa/Cairo')->format('Y-m-d H:i'),
                'event' => self::event($line),
                'by' => $by,
                'note' => $line->reason,
            ];
        }

        return $rows;
    }

    private static function event(ActivityLog $line): string
    {
        $key = 'units.events.'.str_replace('.', '_', $line->action);
        $text = Text::get($key);
        if ($text === 'dashboard.'.$key) {
            return $line->action;
        }
        $after = $line->after ?? [];
        $detail = match ($line->action) {
            'document.reviewed', 'document.viewed' => is_string($after['type'] ?? null) ? DocumentType::tryFrom($after['type'])?->label() : null,
            'unit.team_edit' => implode(app()->getLocale() === 'ar' ? '، ' : ', ', array_map(static fn ($f): string => FieldDisplay::label((string) $f), array_keys($after))),
            default => null,
        };

        return $detail !== null && $detail !== '' ? $text.': '.$detail : $text;
    }
}

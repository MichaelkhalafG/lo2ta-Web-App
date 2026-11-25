<?php

namespace App\Support\Calculator;

use App\Models\DeveloperPolicy;
use App\Models\MarketValue;
use App\Support\Cache\VersionedCache;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The researched calculator values (config/market_values.php, PLAN 4.3): what the public site may show of them
 * (J10: the range or rule and "checked <month year>", never the sources), when one is due for a re-check or under
 * review (J8), and the dashboard's save, which writes the settings and the client's confirmation together.
 */
final class MarketValues
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array<string, array{settings: list<string>, public: string|array<string, string>}>
     */
    public static function definitions(): array
    {
        /** @var array<string, array{settings: list<string>, public: string|array<string, string>}> $values */
        $values = config('market_values.values');

        return $values;
    }

    /** 'current', 'recheck' (6 months after the check) or 'stale' (12 months: "under review" on the site). */
    public static function status(MarketValue $value, ?CarbonImmutable $today = null): string
    {
        $today ??= CarbonImmutable::today();
        $checked = CarbonImmutable::parse($value->checked_on->format('Y-m-d'));
        $stale = config('market_values.stale_months');
        $recheck = config('market_values.recheck_months');
        if ($today->greaterThanOrEqualTo($checked->addMonthsNoOverflow(is_int($stale) ? $stale : 12))) {
            return 'stale';
        }
        if ($today->greaterThanOrEqualTo($checked->addMonthsNoOverflow(is_int($recheck) ? $recheck : 6))) {
            return 'recheck';
        }

        return 'current';
    }

    /**
     * The values the public site lists, in order. Each carries its settings' values, the month it was checked, and
     * whether it is provisional or under review. No sources, no notes, no names (J3, J10).
     *
     * @return list<array{key: string, checked: string, under_review: bool, provisional: bool, values: array<string, int|bool|string>}>
     */
    public function publicList(): array
    {
        $version = VersionedCache::version('settings');
        $today = CarbonImmutable::today()->format('Y-m-d');

        /** @var list<array{key: string, checked: string, under_review: bool, provisional: bool, values: array<string, int|bool|string>}> */
        return VersionedCache::remember('tools', "market:{$version}:{$today}", function (): array {
            $rows = MarketValue::query()->get()->keyBy('key');
            $all = $this->settings->all();
            $out = [];
            foreach (self::definitions() as $key => $def) {
                $row = $rows->get($key);
                if (! $row instanceof MarketValue || ! self::shown($def['public'], $all)) {
                    continue;
                }
                $values = [];
                foreach ($def['settings'] as $setting) {
                    $values[$setting] = $all[$setting]['value'];
                }
                $out[] = [
                    'key' => $key,
                    'checked' => $row->checked_on->format('Y-m'),
                    'under_review' => self::status($row) === 'stale',
                    'provisional' => $row->confirmed_at === null,
                    'values' => $values,
                ];
            }

            return $out;
        });
    }

    /**
     * Developers whose own terms the client approved (J3): the only ones the public calculator may name.
     *
     * @return list<array{slug: string, name: string, checked: string}>
     */
    public function approvedDevelopers(string $locale): array
    {
        /** @var list<array{slug: string, name: string, checked: string}> */
        return VersionedCache::remember('tools', "developers:{$locale}:".VersionedCache::version('places'), static function () use ($locale): array {
            $out = [];
            foreach (DeveloperPolicy::query()->approved()->with('developer')->get() as $policy) {
                if ($policy->developer === null) {
                    continue;
                }
                $out[] = ['slug' => $policy->developer->slug, 'name' => $policy->developer->nameFor($locale), 'checked' => $policy->checked_on->format('Y-m')];
            }
            usort($out, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

            return $out;
        });
    }

    /**
     * Save one value from the dashboard: its settings, the date checked, the note and the client's confirmation.
     * Confirmed clears the provisional flag of every setting of the value; not confirmed sets it. All or nothing.
     *
     * @param  array<string, mixed>  $values  setting key => new value (only this value's settings)
     * @param  positive-int  $adminId
     *
     * @throws InvalidArgumentException a setting refused its value (the message names the setting)
     */
    public function save(MarketValue $row, array $values, string $checkedOn, ?string $note, bool $confirmed, int $adminId): void
    {
        $def = self::definitions()[$row->key] ?? null;
        if ($def === null) {
            throw new InvalidArgumentException("Unknown market value {$row->key}.");
        }

        DB::transaction(function () use ($row, $def, $values, $checkedOn, $note, $confirmed, $adminId): void {
            foreach ($def['settings'] as $setting) {
                $value = array_key_exists($setting, $values) ? $values[$setting] : $this->settings->get($setting);
                $this->settings->set($setting, $value, $confirmed, $adminId);
            }
            $row->checked_on = Carbon::parse($checkedOn);
            $row->note = $note;
            $row->updated_by = $adminId;
            if ($confirmed && $row->confirmed_at === null) {
                $row->confirmed_at = now();
                $row->confirmed_by = $adminId;
            } elseif (! $confirmed) {
                $row->confirmed_at = null;
                $row->confirmed_by = null;
            }
            $row->save();
        });
    }

    /**
     * @param  string|array<string, string>  $rule
     * @param  array<string, array{value: int|bool|string, provisional: bool, group: string, public: bool}>  $all
     */
    private static function shown(string|array $rule, array $all): bool
    {
        if ($rule === 'always') {
            return true;
        }
        if (is_array($rule) && isset($rule['above_zero'])) {
            return ($all[$rule['above_zero']]['value'] ?? 0) > 0;
        }
        if (is_array($rule) && isset($rule['when_on'])) {
            return ($all[$rule['when_on']]['value'] ?? false) === true;
        }

        return false;
    }
}

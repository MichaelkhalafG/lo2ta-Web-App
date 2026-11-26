<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Developer;
use App\Models\DeveloperPolicy;
use App\Models\MarketValue;
use App\Models\MarketValueSource;
use App\Support\Calculator\MarketValues;
use App\Support\Dashboard\Text;
use App\Support\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Calculator settings (PLAN 1.3, Phase 6 step 3; admin only): each researched value with its settings, the date
 * checked, its sources (internal, J10), an internal note and the "confirmed by the client" switch; the display
 * settings (the 50% line, J6, and the present-value line); and the developer policies (J3). Every write is logged.
 */
class CalculatorController extends Controller
{
    /** Display settings: not market values, edited on their own (J6, decided 2026-11-17). */
    private const DISPLAY = ['deep_loss_ratio_bp', 'show_present_value'];

    public function index(Settings $settings): Response
    {
        $all = $settings->all();
        $defs = Settings::definitions();
        $rows = MarketValue::query()->with('sources')->get()->keyBy('key');
        $names = AdminUser::query()->pluck('name', 'id');

        $values = [];
        foreach (MarketValues::definitions() as $key => $def) {
            $row = $rows->get($key);
            if (! $row instanceof MarketValue) {
                continue;
            }
            $values[] = [
                'key' => $key,
                'label' => Text::get("calculator.values.{$key}"),
                'status' => MarketValues::status($row),
                'checked_on' => $row->checked_on->format('Y-m-d'),
                'confirmed' => $row->confirmed_at !== null,
                'confirmed_note' => $row->confirmed_at !== null
                    ? ($row->confirmed_by !== null
                        ? Text::get('calculator.confirmed_by', ['name' => self::name($names->get($row->confirmed_by)), 'date' => $row->confirmed_at->timezone('Africa/Cairo')->format('Y-m-d')])
                        : Text::get('calculator.confirmed_on', ['date' => $row->confirmed_at->timezone('Africa/Cairo')->format('Y-m-d')]))
                    : null,
                'note' => $row->note,
                'settings' => array_map(static fn (string $s): array => self::field($s, $defs[$s], $all[$s]['value']), $def['settings']),
                'sources' => $row->sources->map(static fn (MarketValueSource $s): array => [
                    'id' => $s->id,
                    'ref' => $s->ref,
                    'publisher' => $s->publisher,
                    'url' => $s->url,
                    'published_on' => $s->published_on?->format('Y-m-d'),
                    'read_on' => $s->read_on->format('Y-m-d'),
                    'figure' => $s->figure,
                    'origin' => $s->origin,
                    'counted' => $s->counted,
                ])->values()->all(),
            ];
        }

        $policies = DeveloperPolicy::query()->with('developer')->orderBy('id')->get()->map(static fn (DeveloperPolicy $p): array => [
            'id' => $p->id,
            'developer' => $p->developer?->slug,
            'developer_name' => $p->developer?->nameFor(app()->getLocale()),
            ...array_combine(DeveloperPolicy::OVERRIDES, array_map(static fn (string $k): mixed => $p->getAttribute($k), DeveloperPolicy::OVERRIDES)),
            'source_title' => $p->source_title,
            'source_url' => $p->source_url,
            'source_published_on' => $p->source_published_on?->format('Y-m-d'),
            'checked_on' => $p->checked_on->format('Y-m-d'),
            'approved' => $p->client_approved_at !== null,
            'note' => $p->note,
        ])->values()->all();

        return Inertia::render('Calculator/Index', [
            'values' => $values,
            'display' => [
                'fields' => array_map(static fn (string $s): array => self::field($s, $defs[$s], $all[$s]['value']), self::DISPLAY),
                'confirmed' => ! $all['deep_loss_ratio_bp']['provisional'],
            ],
            'policies' => $policies,
            'developers' => Developer::query()->active()->orderBy(app()->getLocale() === 'en' ? 'name_en' : 'name_ar')->get()
                ->map(static fn (Developer $d): array => ['value' => $d->slug, 'label' => $d->nameFor(app()->getLocale())])->values()->all(),
            'market' => [
                'penalty_basis' => $all['penalty_basis']['value'],
                'refund_rule' => $all['refund_rule']['value'],
                'transfer_fee_mode' => $all['transfer_fee_mode']['value'],
            ],
        ]);
    }

    public function updateValue(Request $request, MarketValues $values, string $key): RedirectResponse
    {
        $row = MarketValue::query()->where('key', $key)->firstOrFail();
        $def = MarketValues::definitions()[$key] ?? abort(404);
        $request->validate([
            'settings' => ['present', 'array'],
            'checked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
            'confirmed' => ['required', 'boolean'],
        ]);
        $input = [];
        foreach ($request->array('settings') as $setting => $value) {
            if (is_string($setting) && in_array($setting, $def['settings'], true)) {
                $input[$setting] = $value;
            }
        }
        self::checkPairs($input);

        try {
            $values->save($row, $input, $request->string('checked_on')->toString(), self::clean($request->input('note')), $request->boolean('confirmed'), self::adminId($request));
        } catch (InvalidArgumentException $e) {
            // Settings name the refused key first ("Setting penalty_max_bp is above ..."): put the error on its field.
            $field = preg_match('/^Setting ([a-z0-9_]+) /', $e->getMessage(), $m) === 1 ? 'settings.'.$m[1] : 'settings';
            throw ValidationException::withMessages([$field => Text::get('calculator.invalid_value')]);
        }

        return back()->with('status', Text::get('calculator.saved', ['name' => Text::get("calculator.values.{$key}")]));
    }

    public function updateDisplay(Request $request, Settings $settings): RedirectResponse
    {
        $request->validate([
            'deep_loss_ratio_bp' => ['required', 'integer', 'min:0', 'max:10000'],
            'show_present_value' => ['required', 'boolean'],
            'confirmed' => ['required', 'boolean'],
        ]);
        $admin = self::adminId($request);
        $line = $request->integer('deep_loss_ratio_bp');
        $confirmed = $request->boolean('confirmed');
        $pv = $request->boolean('show_present_value');
        DB::transaction(static function () use ($settings, $line, $confirmed, $pv, $admin): void {
            $settings->set('deep_loss_ratio_bp', $line, $confirmed, $admin);
            $settings->set('show_present_value', $pv, true, $admin);
        });

        return back()->with('status', Text::get('calculator.display_saved'));
    }

    public function storeSource(Request $request, string $key): RedirectResponse
    {
        $row = MarketValue::query()->where('key', $key)->firstOrFail();
        $last = $row->sources()->max('sort');
        $row->sources()->create([...self::sourceData($request), 'sort' => (is_numeric($last) ? (int) $last : 0) + 1]);

        return back()->with('status', Text::get('calculator.source_saved'));
    }

    public function updateSource(Request $request, string $key, MarketValueSource $source): RedirectResponse
    {
        abort_unless($source->marketValue?->key === $key, 404);
        $source->fill(self::sourceData($request))->save();

        return back()->with('status', Text::get('calculator.source_saved'));
    }

    public function destroySource(string $key, MarketValueSource $source): RedirectResponse
    {
        abort_unless($source->marketValue?->key === $key, 404);
        $source->delete();

        return back()->with('status', Text::get('calculator.source_deleted'));
    }

    public function storePolicy(Request $request): RedirectResponse
    {
        $data = self::policyData($request, null);
        DeveloperPolicy::query()->create($data);

        return back()->with('status', Text::get('calculator.policy_saved'));
    }

    public function updatePolicy(Request $request, DeveloperPolicy $policy): RedirectResponse
    {
        $policy->fill(self::policyData($request, $policy))->save();

        return back()->with('status', Text::get('calculator.policy_saved'));
    }

    public function destroyPolicy(DeveloperPolicy $policy): RedirectResponse
    {
        $policy->delete();

        return back()->with('status', Text::get('calculator.policy_deleted'));
    }

    /**
     * @param  array{type: string, group: string, default: int|bool|string, min?: int, max?: int, options?: list<string>, public?: bool, provisional?: bool, format?: string}  $def
     * @return array{key: string, label: string, type: string, unit: string, value: int|bool|string, min: int|null, max: int|null, options: list<array{value: string, label: string}>}
     */
    private static function field(string $key, array $def, int|bool|string $value): array
    {
        $unit = match (true) {
            str_ends_with($key, '_bp') => 'pct',
            str_contains($key, 'years') => 'years',
            str_ends_with($key, '_fixed') => 'egp',
            default => 'none',
        };

        return [
            'key' => $key,
            'label' => Text::get("setting_labels.{$key}"),
            'type' => $def['type'],
            'unit' => $unit,
            'value' => $value,
            'min' => $def['min'] ?? null,
            'max' => $def['max'] ?? null,
            'options' => array_map(static fn (string $o): array => ['value' => $o, 'label' => Text::get("calculator.options.{$key}.{$o}")], $def['options'] ?? []),
        ];
    }

    /**
     * A range's low end may not pass its high end.
     *
     * @param  array<string, mixed>  $input
     */
    private static function checkPairs(array $input): void
    {
        foreach ([['penalty_min_bp', 'penalty_max_bp'], ['maintenance_deposit_min_bp', 'maintenance_deposit_max_bp']] as [$low, $high]) {
            if (isset($input[$low], $input[$high]) && is_numeric($input[$low]) && is_numeric($input[$high]) && (int) $input[$low] > (int) $input[$high]) {
                throw ValidationException::withMessages(["settings.{$high}" => Text::get('calculator.range_order')]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function sourceData(Request $request): array
    {
        $request->validate([
            'ref' => ['required', 'string', 'max:8', 'regex:/^[A-Za-z0-9-]+$/'],
            'publisher' => ['required', 'string', 'max:160'],
            'url' => ['nullable', 'string', 'max:2048', 'url:https,http'],
            'published_on' => ['nullable', 'date_format:Y-m-d'],
            'read_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'figure' => ['required', 'string', 'max:4000'],
            'origin' => ['nullable', 'string', 'max:64'],
            'counted' => ['required', 'boolean'],
        ]);

        return [
            'ref' => $request->string('ref')->trim()->toString(),
            'publisher' => $request->string('publisher')->trim()->toString(),
            'url' => self::clean($request->input('url')),
            'published_on' => self::clean($request->input('published_on')),
            'read_on' => $request->string('read_on')->toString(),
            'figure' => $request->string('figure')->trim()->toString(),
            'origin' => self::clean($request->input('origin')),
            'counted' => $request->boolean('counted'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function policyData(Request $request, ?DeveloperPolicy $policy): array
    {
        $bp = ['nullable', 'integer', 'min:0', 'max:10000'];
        $request->validate([
            'developer' => ['required', 'string', Rule::exists('developers', 'slug')],
            'penalty_basis' => ['nullable', Rule::in(['total', 'paid'])],
            'penalty_min_bp' => $bp,
            'penalty_max_bp' => $bp,
            'refund_rule' => ['nullable', Rule::in(['lump', 'mirror', 'fixed'])],
            'refund_years_fixed' => ['nullable', 'integer', 'min:0', 'max:20'],
            'late_fee_bp' => $bp,
            'transfer_fee_mode' => ['nullable', Rule::in(['pct', 'fixed'])],
            'transfer_fee_bp' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'transfer_fee_fixed' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'source_title' => ['required', 'string', 'max:255'],
            'source_url' => ['required', 'string', 'max:2048', 'url:https,http'],
            'source_published_on' => ['nullable', 'date_format:Y-m-d'],
            'checked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'approved' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $developer = Developer::query()->where('slug', $request->string('developer')->toString())->firstOrFail();
        $taken = DeveloperPolicy::query()->where('developer_id', $developer->id)
            ->when($policy !== null, static fn ($q) => $q->whereKeyNot($policy instanceof DeveloperPolicy ? $policy->id : 0))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['developer' => Text::get('calculator.policy_exists')]);
        }

        $overrides = [];
        foreach (DeveloperPolicy::OVERRIDES as $key) {
            $overrides[$key] = ! $request->filled($key) ? null
                : (in_array($key, ['penalty_basis', 'refund_rule', 'transfer_fee_mode'], true) ? $request->string($key)->toString() : $request->integer($key));
        }
        if (is_int($overrides['penalty_min_bp']) && is_int($overrides['penalty_max_bp']) && $overrides['penalty_min_bp'] > $overrides['penalty_max_bp']) {
            throw ValidationException::withMessages(['penalty_max_bp' => Text::get('calculator.range_order')]);
        }
        if (array_filter($overrides, static fn (int|string|null $v): bool => $v !== null) === []) {
            throw ValidationException::withMessages(['penalty_basis' => Text::get('calculator.policy_empty')]);
        }

        $admin = self::adminId($request);
        $approved = $request->boolean('approved');
        $kept = $policy !== null && $policy->client_approved_at !== null;

        return [
            ...$overrides,
            'developer_id' => $developer->id,
            'source_title' => $request->string('source_title')->trim()->toString(),
            'source_url' => $request->string('source_url')->trim()->toString(),
            'source_published_on' => self::clean($request->input('source_published_on')),
            'checked_on' => $request->string('checked_on')->toString(),
            'client_approved_at' => $approved ? ($kept ? $policy->client_approved_at : now()) : null,
            'client_approved_by' => $approved ? ($kept ? $policy->client_approved_by : $admin) : null,
            'note' => self::clean($request->input('note')),
            'updated_by' => $admin,
        ];
    }

    private static function clean(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }

    private static function name(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : '—';
    }

    /** @return positive-int */
    private static function adminId(Request $request): int
    {
        $admin = $request->user('admin');
        $id = $admin instanceof AdminUser ? $admin->id : 0;
        abort_unless($id > 0, 403);

        return $id;
    }
}

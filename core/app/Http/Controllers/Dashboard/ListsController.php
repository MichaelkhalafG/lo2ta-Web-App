<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UnitState;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Developer;
use App\Models\Unit;
use App\Support\Dashboard\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Areas, compounds and developers (PLAN 1.3, 3.2, 3.3; part 7.6; G16). Both roles see the lists; an admin adds and edits
 * (can:manage-lists). An area code never changes once a unit uses the area or a district under it (G16, PLAN 2.2.8).
 * Slugs are made from the English name when an entry is created and never change, so public links keep working.
 * Governorates are listed one under the other (K1). The models log every change themselves.
 */
class ListsController extends Controller
{
    public function areas(Request $request): Response
    {
        $all = Area::query()->orderBy('sort')->orderBy('id')->get();
        $used = Unit::query()->where('state', '!=', UnitState::Draft->value)->whereNotNull('area_id')->selectRaw('area_id, count(*) as total')->groupBy('area_id')->pluck('total', 'area_id');
        $rows = [];
        $walk = static function (?int $parent, int $depth) use (&$walk, $all, $used, &$rows): void {
            foreach ($all->where('parent_id', $parent) as $area) {
                $count = $used->get($area->id, 0);
                $rows[] = [
                    'id' => $area->id, 'parent_id' => $area->parent_id, 'depth' => $depth, 'code' => $area->code,
                    'name_ar' => $area->name_ar, 'name_en' => $area->name_en, 'slug' => $area->slug, 'is_active' => $area->is_active,
                    'units' => is_numeric($count) ? (int) $count : 0, 'code_locked' => self::codeLocked($area),
                ];
                $walk($area->id, $depth + 1);
            }
        };
        $walk(null, 0);

        return Inertia::render('Lists/Areas', ['areas' => $rows, 'canEdit' => self::canEdit($request)]);
    }

    public function compounds(Request $request): Response
    {
        $locale = app()->getLocale();
        $used = Unit::query()->where('state', '!=', UnitState::Draft->value)->whereNotNull('compound_id')->selectRaw('compound_id, count(*) as total')->groupBy('compound_id')->pluck('total', 'compound_id');
        $rows = Compound::query()->with(['developer', 'area'])->orderBy('name_en')->get()->map(static fn (Compound $c): array => [
            'id' => $c->id, 'developer_id' => $c->developer_id, 'area_id' => $c->area_id, 'name_ar' => $c->name_ar, 'name_en' => $c->name_en,
            'slug' => $c->slug, 'is_active' => $c->is_active, 'intro_ar' => $c->intro_ar, 'intro_en' => $c->intro_en,
            'intro_is_placeholder' => $c->intro_is_placeholder, 'developer' => $c->developer?->nameFor($locale), 'area' => $c->area?->nameFor($locale),
            'units' => is_numeric($used->get($c->id, 0)) ? (int) $used->get($c->id, 0) : 0,
        ])->values()->all();
        $developers = Developer::query()->orderBy('name_en')->get()->map(static fn (Developer $d): array => ['value' => (string) $d->id, 'label' => $d->nameFor($locale)])->values()->all();
        $areas = Area::query()->whereNotNull('parent_id')->with('parent')->orderBy('sort')->get()
            ->map(static fn (Area $a): array => ['value' => (string) $a->id, 'label' => trim(($a->parent?->nameFor($locale) ?? '').' / '.$a->nameFor($locale), ' /')])->values()->all();

        return Inertia::render('Lists/Compounds', ['compounds' => $rows, 'developers' => $developers, 'areas' => $areas, 'canEdit' => self::canEdit($request)]);
    }

    public function developers(Request $request): Response
    {
        $used = Unit::query()->where('state', '!=', UnitState::Draft->value)->whereNotNull('developer_id')->selectRaw('developer_id, count(*) as total')->groupBy('developer_id')->pluck('total', 'developer_id');
        $rows = Developer::query()->withCount('compounds')->orderBy('name_en')->get()->map(static fn (Developer $d): array => [
            'id' => $d->id, 'name_ar' => $d->name_ar, 'name_en' => $d->name_en, 'slug' => $d->slug, 'is_active' => $d->is_active,
            'compounds' => is_numeric($d->getAttribute('compounds_count')) ? (int) $d->getAttribute('compounds_count') : 0,
            'units' => is_numeric($used->get($d->id, 0)) ? (int) $used->get($d->id, 0) : 0,
        ])->values()->all();

        return Inertia::render('Lists/Developers', ['developers' => $rows, 'canEdit' => self::canEdit($request)]);
    }

    public function saveArea(Request $request, ?Area $area = null): RedirectResponse
    {
        $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('areas', 'id')],
            'name_ar' => ['required', 'string', 'min:2', 'max:120'],
            'name_en' => ['required', 'string', 'min:2', 'max:120'],
            'code' => ['nullable', 'string', 'regex:/^[A-Za-z]{2,4}$/', Rule::unique('areas', 'code')->ignore($area?->id)],
            'is_active' => ['required', 'boolean'],
        ]);
        $typed = strtoupper($request->string('code')->trim()->toString());
        $code = $typed !== '' ? $typed : null;
        $parent = $request->filled('parent_id') ? $request->integer('parent_id') : null;
        if ($area !== null && $parent !== $area->parent_id) {
            throw ValidationException::withMessages(['parent_id' => Text::get('lists.errors.parent_fixed')]);
        }
        if ($area !== null && $code !== $area->code && self::codeLocked($area)) {
            throw ValidationException::withMessages(['code' => Text::get('lists.errors.code_locked')]);
        }
        $target = $area ?? new Area;
        $target->fill([
            'name_ar' => trim((string) $request->string('name_ar')), 'name_en' => trim((string) $request->string('name_en')),
            'code' => $code, 'is_active' => $request->boolean('is_active'),
        ]);
        if ($area === null) {
            $target->fill(['parent_id' => $parent, 'slug' => self::slug(Area::class, $request->string('name_en')->toString()), 'sort' => Area::query()->count() + 1]);
        }
        $target->save();

        return back()->with('status', Text::get('staff.saved'));
    }

    public function saveCompound(Request $request, ?Compound $compound = null): RedirectResponse
    {
        $request->validate([
            'developer_id' => ['required', 'integer', Rule::exists('developers', 'id')],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->whereNotNull('parent_id')],
            'name_ar' => ['required', 'string', 'min:2', 'max:120'],
            'name_en' => ['required', 'string', 'min:2', 'max:120'],
            'intro_ar' => ['nullable', 'string', 'max:4000'],
            'intro_en' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $target = $compound ?? new Compound;
        $introAr = $request->string('intro_ar')->trim()->toString();
        $introEn = $request->string('intro_en')->trim()->toString();
        $target->fill([
            'developer_id' => $request->integer('developer_id'), 'area_id' => $request->integer('area_id'),
            'name_ar' => trim((string) $request->string('name_ar')), 'name_en' => trim((string) $request->string('name_en')),
            'intro_ar' => $introAr !== '' ? $introAr : null, 'intro_en' => $introEn !== '' ? $introEn : null,
            // D30: an intro shows on the public page only once it is no longer a placeholder; one written here is ours.
            'intro_is_placeholder' => $introAr === '' || $introEn === '',
            'is_active' => $request->boolean('is_active'),
        ]);
        if ($compound === null) {
            $target->slug = self::slug(Compound::class, $request->string('name_en')->toString());
        }
        $target->save();

        return back()->with('status', Text::get('staff.saved'));
    }

    public function saveDeveloper(Request $request, ?Developer $developer = null): RedirectResponse
    {
        $request->validate([
            'name_ar' => ['required', 'string', 'min:2', 'max:120'],
            'name_en' => ['required', 'string', 'min:2', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);
        $target = $developer ?? new Developer;
        $target->fill(['name_ar' => trim((string) $request->string('name_ar')), 'name_en' => trim((string) $request->string('name_en')), 'is_active' => $request->boolean('is_active')]);
        if ($developer === null) {
            $target->slug = self::slug(Developer::class, $request->string('name_en')->toString());
        }
        $target->save();

        return back()->with('status', Text::get('staff.saved'));
    }

    /** G16: the code is fixed once any sent unit uses the area or a district under it. */
    private static function codeLocked(Area $area): bool
    {
        $ids = Area::query()->where('parent_id', $area->id)->pluck('id')->push($area->id)->all();

        return Unit::query()->whereIn('area_id', $ids)->whereNotNull('serial')->exists();
    }

    /**
     * @param  class-string<Area>|class-string<Compound>|class-string<Developer>  $model
     */
    private static function slug(string $model, string $name): string
    {
        $base = Str::limit(Str::slug($name), 80, '') ?: 'entry';
        $slug = $base;
        for ($i = 2; $model::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private static function canEdit(Request $request): bool
    {
        return $request->user('admin')?->can('manage-lists') === true;
    }
}

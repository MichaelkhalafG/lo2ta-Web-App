<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TextSource;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\SiteText;
use App\Support\Dashboard\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Site texts (PLAN 1.3, 6; K7, D68, D70; part 7.6; admin only, can:manage-site-texts). Each text in Arabic and English
 * with its state: placeholder, ours (for the client to review), awaiting legal review, or the client's own. The body is
 * plain text with the few marks of D70; nothing is ever read as HTML. Every change is logged by the model itself and
 * the public answers are refreshed (versioned cache).
 */
class SiteTextsController extends Controller
{
    public function index(): Response
    {
        $texts = SiteText::query()->orderBy('group')->orderBy('key')->get()->map(static fn (SiteText $t): array => [
            'id' => $t->id,
            'key' => $t->key,
            'group' => $t->group,
            'ar' => $t->ar,
            'en' => $t->en,
            'source' => $t->source->value,
            'source_label' => $t->source->label(),
            'note' => $t->note,
            'updated_at' => $t->updated_at?->timezone('Africa/Cairo')->format('Y-m-d H:i'),
        ])->values()->all();

        return Inertia::render('SiteTexts/Index', [
            'texts' => $texts,
            'sources' => array_map(static fn (TextSource $s): array => ['value' => $s->value, 'label' => $s->label()], TextSource::cases()),
        ]);
    }

    public function update(Request $request, SiteText $siteText): RedirectResponse
    {
        $request->validate([
            'ar' => ['required', 'string', 'max:20000'],
            'en' => ['required', 'string', 'max:20000'],
            'source' => ['required', Rule::in(TextSource::values())],
        ]);
        $admin = $request->user('admin');
        $source = TextSource::from($request->string('source')->toString());
        $siteText->forceFill([
            'ar' => str_replace("\r\n", "\n", $request->string('ar')->trim()->toString()),
            'en' => str_replace("\r\n", "\n", $request->string('en')->trim()->toString()),
            'source' => $source,
            'is_placeholder' => $source === TextSource::Placeholder,
            'updated_by' => $admin instanceof AdminUser ? $admin->id : null,
        ]);
        if (! $siteText->isDirty(['ar', 'en', 'source'])) {
            return back()->with('status', Text::get('units.done.nothing_changed'));
        }
        $siteText->save();

        return back()->with('status', Text::get('staff.saved'));
    }
}

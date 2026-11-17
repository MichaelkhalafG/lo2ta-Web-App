<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetDashboardLocale;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Switches the dashboard language. A signed-in admin's choice is saved on their account (logged by
 * LogsActivity); a guest's choice lives in the session only.
 */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys((array) config('locales.supported')))],
        ]);
        $locale = $request->string('locale')->toString();

        $admin = Auth::guard('admin')->user();
        if ($admin instanceof AdminUser) {
            $admin->update(['locale' => $locale]);
        } else {
            $request->session()->put(SetDashboardLocale::SESSION_KEY, $locale);
        }

        return back();
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Models\User;
use App\Support\Accounts\Phone;
use App\Support\Activity\ActivityLogger;
use App\Support\Text\PlainLine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * My account (PLAN 2.1): the signed-in user's details, and changes to the name, mobile and language (RUN D13).
 * The e-mail is the sign-in identity and is not changed here.
 */
class MeController extends Controller
{
    public function show(Request $request): AccountResource
    {
        return new AccountResource(self::user($request));
    }

    public function update(Request $request, ActivityLogger $log): AccountResource
    {
        $user = self::user($request);
        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', PlainLine::RULE],
            'phone' => ['required', 'string', 'max:30'],
            'locale' => ['required', 'in:ar,en'],
        ]);
        $phone = Phone::normalize($request->string('phone')->toString());
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('validation.custom.phone.phone')]);
        }

        $before = ['name' => $user->name, 'phone' => $user->phone, 'locale' => $user->locale];
        $user->fill([
            'name' => trim($request->string('name')->toString()),
            'phone' => $phone,
            'locale' => $request->string('locale')->toString(),
        ]);
        if ($user->isDirty()) {
            $after = $user->getDirty();
            $user->save();
            $log->record('user.profile_updated', $user, array_intersect_key($before, $after), $after);
        }

        return new AccountResource($user);
    }

    public static function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}

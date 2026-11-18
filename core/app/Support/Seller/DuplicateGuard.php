<?php

namespace App\Support\Seller;

use App\Models\DuplicateAttempt;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The duplicate-contract block (PLAN 3.4 A, 2.2.13 point 4): at the end of step 1 and again on submit. A match is
 * another unit that holds the key (submitted, changes requested, published or reserved). The message never says which
 * unit matched; every hit is logged for the admin; checks are rate limited per user by the caller.
 */
final class DuplicateGuard
{
    public function check(Unit $unit, User $user, string $stage): void
    {
        $keys = ContractKeys::of($unit);
        if ($keys === null) {
            return;
        }
        $unit->forceFill(['contract_key' => $keys['contract'], 'unit_key' => $keys['unit']]);

        $match = Unit::query()
            ->whereKeyNot($unit->getKey())
            ->where(fn ($q) => $q->where('active_contract_key', $keys['contract'])->orWhere('active_unit_key', $keys['unit']))
            ->first(['id', 'active_contract_key']);
        if (! $match instanceof Unit) {
            return;
        }

        DuplicateAttempt::query()->create([
            'user_id' => $user->id,
            'unit_id' => $unit->exists ? $unit->id : null,
            'matched_unit_id' => $match->id,
            'key_kind' => $match->active_contract_key === $keys['contract'] ? 'contract' : 'unit',
            'stage' => $stage,
            'blocked' => true,
        ]);

        throw ValidationException::withMessages(['contract_number' => self::message()]);
    }

    public static function message(): string
    {
        $text = __('seller.duplicate');

        return is_string($text) ? $text : 'seller.duplicate';
    }
}

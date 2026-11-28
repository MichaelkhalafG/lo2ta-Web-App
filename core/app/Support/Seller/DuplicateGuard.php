<?php

namespace App\Support\Seller;

use App\Models\DuplicateAttempt;
use App\Models\DuplicateOverride;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The duplicate-contract block (PLAN 3.4 A, 2.2.13 point 4): at the end of step 1 and again on submit. A match is
 * another unit that holds the key (submitted, changes requested, published or reserved). The message never says which
 * unit matched; every hit is logged for the admin; checks are rate limited per user by the caller.
 *
 * An admin's override (Q23, D113-D114) excuses one blocked unit from the one unit it matched, nothing more. A unit
 * with an override keeps its active keys with a "#u<id>" suffix so both can be active under the unique columns; every
 * check compares by prefix, so either unit still blocks a third listing of the same contract.
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

        $match = self::match($unit, $keys, ['id', 'active_contract_key']);
        if (! $match instanceof Unit) {
            return;
        }

        DuplicateAttempt::query()->create([
            'user_id' => $user->id,
            'unit_id' => $unit->exists ? $unit->id : null,
            'matched_unit_id' => $match->id,
            'key_kind' => str_starts_with((string) $match->active_contract_key, $keys['contract']) ? 'contract' : 'unit',
            'stage' => $stage,
            'blocked' => true,
        ]);

        throw ValidationException::withMessages(['contract_number' => self::message()]);
    }

    /**
     * Another active unit holding either key, leaving out the units an admin excused this one from.
     *
     * @param  array{contract: string, unit: string}  $keys
     * @param  list<string>  $columns
     */
    public static function match(Unit $unit, array $keys, array $columns): ?Unit
    {
        $excused = $unit->exists
            ? DuplicateOverride::query()->where('unit_id', $unit->id)->whereNotNull('matched_unit_id')->pluck('matched_unit_id')->all()
            : [];
        $query = Unit::query()
            ->when($unit->exists, fn ($q) => $q->whereKeyNot($unit->getKey()))
            ->when($excused !== [], fn ($q) => $q->whereKeyNot($excused))
            // The keys are hex digests, so they hold no LIKE wildcards; a prefix LIKE uses the unique indexes.
            ->where(fn ($q) => $q->where('active_contract_key', 'like', $keys['contract'].'%')->orWhere('active_unit_key', 'like', $keys['unit'].'%'));

        return $query->first($columns);
    }

    /**
     * The keys a unit holds while active: as they are, or with its own suffix when an admin overrode a block on it.
     *
     * @param  array{contract: string, unit: string}  $keys
     * @return array{active_contract_key: string, active_unit_key: string}
     */
    public static function activeKeys(Unit $unit, array $keys): array
    {
        $suffix = $unit->exists && DuplicateOverride::query()->where('unit_id', $unit->id)->exists() ? '#u'.$unit->id : '';

        return ['active_contract_key' => $keys['contract'].$suffix, 'active_unit_key' => $keys['unit'].$suffix];
    }

    public static function message(): string
    {
        $text = __('seller.duplicate');

        return is_string($text) ? $text : 'seller.duplicate';
    }
}

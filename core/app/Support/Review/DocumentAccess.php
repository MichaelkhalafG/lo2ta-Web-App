<?php

namespace App\Support\Review;

use App\Enums\UnitState;
use App\Models\AdminUser;
use App\Models\UnitDocument;

/**
 * Who may open a unit's document (G2, Michael 2026-11-27). National ID copies: both staff roles while the unit is under
 * review (submitted, changes requested) or in an open deal; only an admin otherwise. Every other document: both roles.
 * A purged file is gone for everyone. Every opening is logged by the caller (FilesController).
 */
final class DocumentAccess
{
    /** States in which a reviewer may open the national ID copies. Part 7.5 adds "an open deal" through Deal. */
    private const REVIEWER_ID_STATES = [UnitState::Submitted, UnitState::ChangesRequested, UnitState::Reserved];

    public static function mayOpen(AdminUser $admin, UnitDocument $document): bool
    {
        if (! $admin->is_active || ! is_string($document->path) || $document->purged_at !== null) {
            return false;
        }
        if (! $document->type->isNationalId() || $admin->isAdmin()) {
            return true;
        }
        $unit = $document->unit;

        return $unit !== null && (in_array($unit->state, self::REVIEWER_ID_STATES, true) || OpenDeals::has($unit));
    }
}

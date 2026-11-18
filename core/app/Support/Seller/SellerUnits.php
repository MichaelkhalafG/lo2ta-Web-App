<?php

namespace App\Support\Seller;

use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The only way a seller route reaches a unit, a document or a photo (Michael 2026-11-18): by public ID AND owner in
 * the same query. Another seller's row, a guessed ID and a missing row all answer the same 404, so a probe learns
 * nothing about whether the row exists.
 */
final class SellerUnits
{
    public const ID = '[0-9A-HJKMNP-TV-Z]{26}';

    public static function unit(User $user, string $publicId): Unit
    {
        $unit = Unit::query()->where('public_id', $publicId)->where('seller_id', $user->id)->first();
        if (! $unit instanceof Unit) {
            throw new NotFoundHttpException;
        }

        return $unit;
    }

    public static function document(Unit $unit, string $publicId): UnitDocument
    {
        $document = $unit->documents()->where('public_id', $publicId)->first();
        if (! $document instanceof UnitDocument) {
            throw new NotFoundHttpException;
        }

        return $document;
    }

    public static function photo(Unit $unit, string $publicId): UnitPhoto
    {
        $photo = $unit->photos()->where('public_id', $publicId)->first();
        if (! $photo instanceof UnitPhoto) {
            throw new NotFoundHttpException;
        }

        return $photo;
    }
}

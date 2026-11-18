<?php

namespace App\Support\Seller;

use App\Enums\DeclarationKind;
use App\Models\SiteText;
use App\Models\Unit;
use App\Models\User;

/**
 * The seller's declarations (PLAN 2.2.1, 2.2.14). Each acceptance is a row with the text version, the time and the
 * IP. The version is computed on the server from the text shown (both languages), never sent by the client, so a
 * wording change gives new rows a new version.
 */
final class Declarations
{
    public const SUBMIT = [DeclarationKind::OwnerNotBroker, DeclarationKind::DataMatches, DeclarationKind::TermsPrivacy];

    private const KEYS = [
        'no_over' => 'seller.no_over.body',
        'owner_not_broker' => 'seller.declaration.owner_not_broker',
        'data_matches' => 'seller.declaration.data_matches',
        'terms_privacy' => 'seller.declaration.terms_privacy',
    ];

    public static function version(DeclarationKind $kind): string
    {
        $text = SiteText::query()->where('key', self::KEYS[$kind->value])->first(['ar', 'en']);
        $body = $text instanceof SiteText ? $text->ar."\n".$text->en : 'missing:'.$kind->value;

        return substr(hash('sha256', $body), 0, 16);
    }

    public static function record(Unit $unit, User $user, DeclarationKind $kind, string $ip): void
    {
        $unit->declarations()->create([
            'user_id' => $user->id,
            'kind' => $kind,
            'text_version' => self::version($kind),
            'accepted_at' => now(),
            'ip' => $ip,
        ]);
    }
}

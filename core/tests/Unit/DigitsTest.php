<?php

use App\Support\Accounts\Phone;
use App\Support\Text\Digits;

// Audit C2: one digit map for the whole core; every former copy now goes through it.
it('turns Arabic-Indic and Persian digits into Latin ones and leaves the rest alone', function () {
    expect(Digits::latin('٠١٢٣٤٥٦٧٨٩ ۰۱۲۳۴۵۶۷۸۹ abc 42'))->toBe('0123456789 0123456789 abc 42')
        ->and(Phone::normalize('۰۱۰۱۲۳۴۵۶۷۸'))->toBe('01012345678');
});

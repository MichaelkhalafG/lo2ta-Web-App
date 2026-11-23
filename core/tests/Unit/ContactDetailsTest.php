<?php

use App\Support\Text\ContactDetails;

/*
 * Audit M7 (proofs/contact-filter.php): contact details hidden with fullwidth digits, invisible characters, emoji
 * keycaps, a letter O, Arabic commas, a mobile without its 0, "at ... dot com" or a social handle got through.
 * Numbers spelled out in words stay with the team's review (the description is read before publishing).
 */

it('catches every way the audit hid a contact detail', function (string $text) {
    expect(ContactDetails::has($text))->toBeTrue()
        ->and(ContactDetails::mask($text))->toContain(ContactDetails::MASK);
})->with([
    'plain mobile' => ['call 01012345678'],
    'Arabic digits' => ['كلمني ٠١٠١٢٣٤٥٦٧٨'],
    'spaced' => ['0 1 0 1 2 3 4 5 6 7 8'],
    'mixed scripts' => ['01٠12345678'],
    'letter O' => ['O1O12345678'],
    'fullwidth digits' => ['０１０１２３４５６７８'],
    'emoji keycaps' => ['0️⃣1️⃣0️⃣1️⃣2️⃣3️⃣4️⃣5️⃣6️⃣7️⃣8️⃣'],
    'without the leading 0' => ['1012345678 واتساب'],
    'zero-width space' => ["0101\u{200B}2345678"],
    'direction marks' => ["010\u{200F}1234\u{200E}5678"],
    'Arabic commas' => ['010،1234،5678'],
    'at and dot in words' => ['me at gmail dot com'],
    '(at)' => ['ahmed (at) gmail.com'],
    'wa.me link' => ['wa.me/201012345678'],
    'telegram link' => ['t.me/ahmedunits'],
    'facebook handle' => ['facebook/ahmed.sale'],
    'instagram handle in Arabic' => ['انستا @ahmed_units'],
    '+20 with dashes' => ['+20-101-234-5678'],
]);

it('leaves ordinary unit descriptions alone', function (string $text) {
    expect(ContactDetails::has($text))->toBeFalse()
        ->and(ContactDetails::mask($text))->toBe($text);
})->with([
    'price with commas' => ['السعر 1,000,000,000 جنيه'],
    'price in spaced groups' => ['مقدم 1 000 000 000'],
    'price in dotted groups' => ['السعر 1.500.000 والقسط 112.500'],
    'sizes and years' => ['المساحة 145 متر والدور 3 والتسليم 2027'],
    'unit code' => ['كود الوحدة LK-NC-0124'],
    'address with an Arabic comma' => ['العنوان: 5 شارع 90، التجمع'],
    'a word with O next to a digit' => ['Studio1 near 2025, zoo2025'],
    'room numbers' => ['Room 1001 floor 2'],
]);

it('still leaves numbers spelled out in words to the review', function () {
    expect(ContactDetails::has('صفر واحد صفر واحد اتنين تلاتة اربعة خمسة ستة سبعة تمانية'))->toBeFalse();
});

<?php

it('lists Arabic first and English second', function () {
    $locales = require __DIR__.'/../../config/locales.php';

    expect(array_keys($locales['supported']))->toBe(['ar', 'en'])
        ->and($locales['supported']['ar']['dir'])->toBe('rtl')
        ->and($locales['supported']['en']['dir'])->toBe('ltr')
        ->and($locales['default'])->toBe('ar')
        ->and($locales['supported']['ar']['site_name'])->toBe('لقطة')
        ->and($locales['supported']['en']['site_name'])->toBe('Lokta');
});

<?php

/*
| Areas seed (PLAN 3.3): governorate => its areas, each with a code used in unit codes (LK-{code}-{serial}).
| DRAFT FOR MICHAEL'S REVIEW (2026-11-17): the markets where installment compounds are sold today. The dashboard
| edits the list after seeding; the seeder only adds missing slugs and never overwrites an edited row.
| Codes are 2-3 Latin capitals, unique, and never change once a unit uses them.
*/

return [
    ['slug' => 'cairo', 'ar' => 'القاهرة', 'en' => 'Cairo', 'areas' => [
        ['slug' => 'new-cairo', 'code' => 'NC', 'ar' => 'القاهرة الجديدة', 'en' => 'New Cairo'],
        ['slug' => 'new-capital', 'code' => 'AC', 'ar' => 'العاصمة الإدارية الجديدة', 'en' => 'New Administrative Capital'],
        ['slug' => 'mostakbal-city', 'code' => 'MC', 'ar' => 'مدينة المستقبل', 'en' => 'Mostakbal City'],
        ['slug' => 'madinaty', 'code' => 'MD', 'ar' => 'مدينتي', 'en' => 'Madinaty'],
        ['slug' => 'el-shorouk', 'code' => 'SH', 'ar' => 'الشروق', 'en' => 'El Shorouk'],
        ['slug' => 'el-obour', 'code' => 'OB', 'ar' => 'العبور', 'en' => 'El Obour'],
        ['slug' => 'new-heliopolis', 'code' => 'NH', 'ar' => 'هليوبوليس الجديدة', 'en' => 'New Heliopolis'],
        ['slug' => 'nasr-city', 'code' => 'NS', 'ar' => 'مدينة نصر', 'en' => 'Nasr City'],
    ]],
    ['slug' => 'giza', 'ar' => 'الجيزة', 'en' => 'Giza', 'areas' => [
        ['slug' => 'sheikh-zayed', 'code' => 'SZ', 'ar' => 'الشيخ زايد', 'en' => 'Sheikh Zayed'],
        ['slug' => 'new-zayed', 'code' => 'NZ', 'ar' => 'زايد الجديدة', 'en' => 'New Zayed'],
        ['slug' => '6th-of-october', 'code' => 'OC', 'ar' => 'السادس من أكتوبر', 'en' => '6th of October'],
        ['slug' => 'october-gardens', 'code' => 'OG', 'ar' => 'حدائق أكتوبر', 'en' => 'October Gardens'],
    ]],
    ['slug' => 'matrouh', 'ar' => 'مطروح (الساحل الشمالي)', 'en' => 'Matrouh (North Coast)', 'areas' => [
        ['slug' => 'north-coast', 'code' => 'SL', 'ar' => 'الساحل الشمالي', 'en' => 'North Coast'],
        ['slug' => 'new-alamein', 'code' => 'NA', 'ar' => 'العلمين الجديدة', 'en' => 'New Alamein'],
        ['slug' => 'ras-el-hekma', 'code' => 'RH', 'ar' => 'رأس الحكمة', 'en' => 'Ras El Hekma'],
        ['slug' => 'sidi-abdel-rahman', 'code' => 'SA', 'ar' => 'سيدي عبد الرحمن', 'en' => 'Sidi Abdel Rahman'],
    ]],
    ['slug' => 'suez', 'ar' => 'السويس', 'en' => 'Suez', 'areas' => [
        ['slug' => 'ain-sokhna', 'code' => 'SK', 'ar' => 'العين السخنة', 'en' => 'Ain Sokhna'],
        ['slug' => 'galala', 'code' => 'GL', 'ar' => 'الجلالة', 'en' => 'Galala'],
    ]],
    ['slug' => 'red-sea', 'ar' => 'البحر الأحمر', 'en' => 'Red Sea', 'areas' => [
        ['slug' => 'hurghada', 'code' => 'HG', 'ar' => 'الغردقة', 'en' => 'Hurghada'],
        ['slug' => 'el-gouna', 'code' => 'GN', 'ar' => 'الجونة', 'en' => 'El Gouna'],
        ['slug' => 'sahl-hasheesh', 'code' => 'HS', 'ar' => 'سهل حشيش', 'en' => 'Sahl Hasheesh'],
        ['slug' => 'soma-bay', 'code' => 'SB', 'ar' => 'سوما باي', 'en' => 'Soma Bay'],
    ]],
    ['slug' => 'alexandria', 'ar' => 'الإسكندرية', 'en' => 'Alexandria', 'areas' => [
        ['slug' => 'smouha', 'code' => 'SM', 'ar' => 'سموحة', 'en' => 'Smouha'],
        ['slug' => 'borg-el-arab', 'code' => 'BA', 'ar' => 'برج العرب', 'en' => 'Borg El Arab'],
    ]],
    ['slug' => 'south-sinai', 'ar' => 'جنوب سيناء', 'en' => 'South Sinai', 'areas' => [
        ['slug' => 'sharm-el-sheikh', 'code' => 'SS', 'ar' => 'شرم الشيخ', 'en' => 'Sharm El Sheikh'],
    ]],
];

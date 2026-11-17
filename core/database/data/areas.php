<?php

/*
| Areas seed (PLAN 3.3, decided 2026-11-17): three levels, governorate > area > district.
| The CODE sits on the area (level 2) and is used in unit codes LK-{code}-{serial}; a district (level 3) has no code
| and a unit placed in a district takes its area's code (Fifth Settlement -> NC).
| DRAFT FOR MICHAEL AND THE CLIENT TO COMPLETE. The dashboard edits the list after seeding; the seeder only adds
| missing slugs and never overwrites an edited row. Codes are 2 Latin capitals, unique, and never change once a unit
| uses them.
*/

return [
    ['slug' => 'cairo', 'ar' => 'القاهرة', 'en' => 'Cairo', 'areas' => [
        ['slug' => 'new-cairo', 'code' => 'NC', 'ar' => 'القاهرة الجديدة', 'en' => 'New Cairo', 'districts' => [
            ['slug' => 'fifth-settlement', 'ar' => 'التجمع الخامس', 'en' => 'Fifth Settlement'],
            ['slug' => 'first-settlement', 'ar' => 'التجمع الأول', 'en' => 'First Settlement'],
            ['slug' => 'third-settlement', 'ar' => 'التجمع الثالث', 'en' => 'Third Settlement'],
            ['slug' => 'el-rehab', 'ar' => 'الرحاب', 'en' => 'El Rehab'],
            ['slug' => 'katameya', 'ar' => 'القطامية', 'en' => 'Katameya'],
            ['slug' => 'el-narges', 'ar' => 'النرجس', 'en' => 'El Narges'],
            ['slug' => 'el-yasmeen', 'ar' => 'الياسمين', 'en' => 'El Yasmeen'],
            ['slug' => 'el-lotus', 'ar' => 'اللوتس', 'en' => 'El Lotus'],
            ['slug' => 'el-andalus', 'ar' => 'الأندلس', 'en' => 'El Andalus'],
            ['slug' => 'beit-el-watan', 'ar' => 'بيت الوطن', 'en' => 'Beit El Watan'],
            ['slug' => 'golden-square', 'ar' => 'المربع الذهبي', 'en' => 'Golden Square'],
        ]],
        ['slug' => 'new-capital', 'code' => 'AC', 'ar' => 'العاصمة الإدارية الجديدة', 'en' => 'New Administrative Capital', 'districts' => [
            ['slug' => 'new-capital-r3', 'ar' => 'الحي السكني الثالث (R3)', 'en' => 'Residential District 3 (R3)'],
            ['slug' => 'new-capital-r5', 'ar' => 'الحي السكني الخامس (R5)', 'en' => 'Residential District 5 (R5)'],
            ['slug' => 'new-capital-r7', 'ar' => 'الحي السكني السابع (R7)', 'en' => 'Residential District 7 (R7)'],
            ['slug' => 'new-capital-r8', 'ar' => 'الحي السكني الثامن (R8)', 'en' => 'Residential District 8 (R8)'],
            ['slug' => 'new-capital-downtown', 'ar' => 'داون تاون', 'en' => 'Downtown'],
            ['slug' => 'new-capital-cbd', 'ar' => 'حي المال والأعمال', 'en' => 'Central Business District'],
        ]],
        ['slug' => 'mostakbal-city', 'code' => 'MC', 'ar' => 'مدينة المستقبل', 'en' => 'Mostakbal City', 'districts' => []],
        ['slug' => 'madinaty', 'code' => 'MD', 'ar' => 'مدينتي', 'en' => 'Madinaty', 'districts' => []],
        ['slug' => 'el-shorouk', 'code' => 'SH', 'ar' => 'الشروق', 'en' => 'El Shorouk', 'districts' => []],
        ['slug' => 'badr-city', 'code' => 'BD', 'ar' => 'مدينة بدر', 'en' => 'Badr City', 'districts' => []],
        ['slug' => 'new-heliopolis', 'code' => 'NH', 'ar' => 'هليوبوليس الجديدة', 'en' => 'New Heliopolis', 'districts' => []],
        ['slug' => 'heliopolis', 'code' => 'HL', 'ar' => 'مصر الجديدة', 'en' => 'Heliopolis', 'districts' => []],
        ['slug' => 'nasr-city', 'code' => 'NS', 'ar' => 'مدينة نصر', 'en' => 'Nasr City', 'districts' => []],
        ['slug' => 'maadi', 'code' => 'MA', 'ar' => 'المعادي', 'en' => 'Maadi', 'districts' => [
            ['slug' => 'maadi-degla', 'ar' => 'دجلة المعادي', 'en' => 'Degla Maadi'],
            ['slug' => 'zahraa-el-maadi', 'ar' => 'زهراء المعادي', 'en' => 'Zahraa El Maadi'],
        ]],
        ['slug' => 'mokattam', 'code' => 'MK', 'ar' => 'المقطم', 'en' => 'Mokattam', 'districts' => []],
    ]],
    ['slug' => 'giza', 'ar' => 'الجيزة', 'en' => 'Giza', 'areas' => [
        ['slug' => 'sheikh-zayed', 'code' => 'SZ', 'ar' => 'الشيخ زايد', 'en' => 'Sheikh Zayed', 'districts' => []],
        ['slug' => 'new-zayed', 'code' => 'NZ', 'ar' => 'زايد الجديدة', 'en' => 'New Zayed', 'districts' => []],
        ['slug' => '6th-of-october', 'code' => 'OC', 'ar' => 'السادس من أكتوبر', 'en' => '6th of October', 'districts' => []],
        ['slug' => 'october-gardens', 'code' => 'OG', 'ar' => 'حدائق أكتوبر', 'en' => 'October Gardens', 'districts' => []],
        ['slug' => 'smart-village', 'code' => 'SV', 'ar' => 'القرية الذكية', 'en' => 'Smart Village', 'districts' => []],
    ]],
    ['slug' => 'qalyubia', 'ar' => 'القليوبية', 'en' => 'Qalyubia', 'areas' => [
        ['slug' => 'el-obour', 'code' => 'OB', 'ar' => 'العبور', 'en' => 'El Obour', 'districts' => []],
    ]],
    ['slug' => 'sharqia', 'ar' => 'الشرقية', 'en' => 'Sharqia', 'areas' => [
        ['slug' => '10th-of-ramadan', 'code' => 'TR', 'ar' => 'العاشر من رمضان', 'en' => '10th of Ramadan', 'districts' => []],
    ]],
    ['slug' => 'alexandria', 'ar' => 'الإسكندرية', 'en' => 'Alexandria', 'areas' => [
        ['slug' => 'smouha', 'code' => 'SM', 'ar' => 'سموحة', 'en' => 'Smouha', 'districts' => []],
        ['slug' => 'sidi-gaber', 'code' => 'SG', 'ar' => 'سيدي جابر', 'en' => 'Sidi Gaber', 'districts' => []],
        ['slug' => 'san-stefano', 'code' => 'ST', 'ar' => 'سان ستيفانو', 'en' => 'San Stefano', 'districts' => []],
        ['slug' => 'montaza', 'code' => 'MZ', 'ar' => 'المنتزه', 'en' => 'Montaza', 'districts' => []],
        ['slug' => 'agami', 'code' => 'AG', 'ar' => 'العجمي', 'en' => 'Agami', 'districts' => []],
        ['slug' => 'king-mariout', 'code' => 'KM', 'ar' => 'كينج مريوط', 'en' => 'King Mariout', 'districts' => []],
        ['slug' => 'borg-el-arab', 'code' => 'BA', 'ar' => 'برج العرب الجديدة', 'en' => 'New Borg El Arab', 'districts' => []],
    ]],
    ['slug' => 'dakahlia', 'ar' => 'الدقهلية', 'en' => 'Dakahlia', 'areas' => [
        ['slug' => 'new-mansoura', 'code' => 'NM', 'ar' => 'المنصورة الجديدة', 'en' => 'New Mansoura', 'districts' => []],
    ]],
    ['slug' => 'matrouh', 'ar' => 'مطروح (الساحل الشمالي)', 'en' => 'Matrouh (North Coast)', 'areas' => [
        ['slug' => 'north-coast', 'code' => 'SL', 'ar' => 'الساحل الشمالي', 'en' => 'North Coast', 'districts' => []],
        ['slug' => 'new-alamein', 'code' => 'NA', 'ar' => 'العلمين الجديدة', 'en' => 'New Alamein', 'districts' => []],
        ['slug' => 'sidi-abdel-rahman', 'code' => 'SA', 'ar' => 'سيدي عبد الرحمن', 'en' => 'Sidi Abdel Rahman', 'districts' => []],
        ['slug' => 'el-dabaa', 'code' => 'DB', 'ar' => 'الضبعة', 'en' => 'El Dabaa', 'districts' => []],
        ['slug' => 'ras-el-hekma', 'code' => 'RH', 'ar' => 'رأس الحكمة', 'en' => 'Ras El Hekma', 'districts' => []],
        ['slug' => 'marsa-matrouh', 'code' => 'MM', 'ar' => 'مرسى مطروح', 'en' => 'Marsa Matrouh', 'districts' => []],
    ]],
    ['slug' => 'suez', 'ar' => 'السويس', 'en' => 'Suez', 'areas' => [
        ['slug' => 'ain-sokhna', 'code' => 'SK', 'ar' => 'العين السخنة', 'en' => 'Ain Sokhna', 'districts' => []],
        ['slug' => 'galala', 'code' => 'GL', 'ar' => 'الجلالة', 'en' => 'Galala', 'districts' => []],
    ]],
    ['slug' => 'red-sea', 'ar' => 'البحر الأحمر', 'en' => 'Red Sea', 'areas' => [
        ['slug' => 'hurghada', 'code' => 'HG', 'ar' => 'الغردقة', 'en' => 'Hurghada', 'districts' => []],
        ['slug' => 'el-gouna', 'code' => 'GN', 'ar' => 'الجونة', 'en' => 'El Gouna', 'districts' => []],
        ['slug' => 'sahl-hasheesh', 'code' => 'HS', 'ar' => 'سهل حشيش', 'en' => 'Sahl Hasheesh', 'districts' => []],
        ['slug' => 'makadi-bay', 'code' => 'MB', 'ar' => 'مكادي', 'en' => 'Makadi Bay', 'districts' => []],
        ['slug' => 'soma-bay', 'code' => 'SB', 'ar' => 'سوما باي', 'en' => 'Soma Bay', 'districts' => []],
        ['slug' => 'marsa-alam', 'code' => 'MR', 'ar' => 'مرسى علم', 'en' => 'Marsa Alam', 'districts' => []],
    ]],
    ['slug' => 'south-sinai', 'ar' => 'جنوب سيناء', 'en' => 'South Sinai', 'areas' => [
        ['slug' => 'sharm-el-sheikh', 'code' => 'SS', 'ar' => 'شرم الشيخ', 'en' => 'Sharm El Sheikh', 'districts' => []],
        ['slug' => 'dahab', 'code' => 'DH', 'ar' => 'دهب', 'en' => 'Dahab', 'districts' => []],
    ]],
];

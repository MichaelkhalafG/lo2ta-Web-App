<?php

/*
| The market values as researched on 2026-11-24 (docs/research/market-values-2026-11.md, PLAN 4.3). The client
| answered and confirmed five of them on 2026-11-25 (relayed by Michael): 'confirmed_at' carries that date. The
| discount rate stays provisional (used only if the present-value line is switched on). The legal notes from S10 and
| S11 (full refund when the developer is at fault; no automatic cancellation after 75% paid) are approved for texts
| (PLAN K2); they are not values and change no figure.
| Sources are internal (J10). 'origin' groups sources that report the same study or the same article, which count
| as one (J1). 'counted' => false marks a reference that is not counted for the range. Page copies of every counted
| source are kept outside the repository in research-raw/market-values-2026-11/.
| The seeder adds a value that is missing and never changes one that exists (the dashboard owns them).
*/

$study = 'saada-abu-el-kamsan-study';
$read = '2026-11-24';
$client = '2026-11-25 12:00:00';

$s1 = ['ref' => 'S1', 'publisher' => 'Masrawy', 'url' => 'https://www.masrawy.com/news/realestate-news/details/2026/9/17/3049637/', 'published_on' => '2026-09-17', 'read_on' => $read, 'origin' => $study,
    'figure' => 'Saada & Abu El Kamsan law-firm study: "ارتفعت غرامات الفسخ والمصادرة المفروضة على العميل عبر أجيال العقود التي جرى تحليلها من 10% إلى 15% ثم 20%، وصولًا إلى 30% من إجمالي ثمن الوحدة"; "نسبة المصادرة تُحسب من إجمالي ثمن الوحدة وليس فقط المبالغ التي سددها العميل"'];
$s2 = ['ref' => 'S2', 'publisher' => 'Masrawy', 'url' => 'https://www.masrawy.com/news/realestate-news/details/2026/9/19/3050259/', 'published_on' => '2026-09-19', 'read_on' => $read, 'origin' => $study,
    'figure' => 'Same study: "رسوم التنازل عن الوحدات عند إعادة بيعها تتراوح في العقود التي جرى تحليلها بين 1% و5% من قيمة الوحدة"'];
$s3 = ['ref' => 'S3', 'publisher' => 'Egypt Telegraph', 'url' => 'https://www.egypttelegraph.com/article/279824/', 'published_on' => '2026-09-19', 'read_on' => $read, 'origin' => $study,
    'figure' => 'Same study: "ودائع صيانة تصل في بعض العقود إلى 10% من ثمن الوحدة"'];
$s4 = ['ref' => 'S4', 'publisher' => 'Twasl News', 'url' => 'https://twaslnews1.twaslnews.com/4724860/', 'published_on' => '2026-09-06', 'read_on' => $read, 'origin' => 'twasl-4724860',
    'figure' => 'One buyer\'s cancellation (names a developer; internal only, J3): "الشركة أبلغته بخصم 10% من إجمالي قيمة الوحدة ... وليس 10% من إجمالي المبالغ التي سبق له سدادها"'];
$s5 = ['ref' => 'S5', 'publisher' => 'Youm7', 'url' => 'https://www.youm7.com/story/2026/11/8/%D8%AD%D9%83%D9%85-%D8%A8%D9%81%D8%B3%D8%AE-%D8%B9%D9%82%D8%AF-%D9%88%D8%AD%D8%AF%D8%A9-%D8%B3%D9%83%D9%86%D9%8A%D8%A9-%D8%A8%D8%A7%D9%84%D9%85%D9%82%D8%B7%D9%85-%D9%88%D8%A5%D9%84%D8%B2%D8%A7%D9%85-%D8%B4%D8%B1%D9%83%D8%A9-%D8%B9%D9%82%D8%A7%D8%B1%D9%8A%D8%A9-%D8%A8%D8%B1%D8%AF/7610511', 'published_on' => '2026-11-08', 'read_on' => $read, 'origin' => 'youm7-7610511',
    'figure' => 'South Cairo court, case 5488/2026, session 2026-10-28: "الشرط الجزائي المنصوص عليه في البند التاسع عشر من العقد، بنسبة 10% من إجمالي ثمن الوحدة"'];
$s6 = ['ref' => 'S6', 'publisher' => 'Daraj', 'url' => 'https://daraj.media/en/property-exit-breaking-out-of-egypts-real-estate-bubble/', 'published_on' => '2026-09-23', 'read_on' => $read, 'origin' => 'daraj-property-exit',
    'figure' => '"deduct a percentage of the unit\'s value if the contract is canceled, and return the remaining amount only through installments spread over a lengthy period"'];
$s7 = ['ref' => 'S7', 'publisher' => 'Banker.news', 'url' => 'https://www.banker.news/152471', 'published_on' => '2026-09-15', 'read_on' => $read, 'origin' => 'banker-152471',
    'figure' => 'Reda El-Menshawy, court of appeal lawyer: "النسب المتداولة لرسوم التنازل تتراوح عادة بين 5% و10%، فيما تصل لدى بعض الشركات إلى 15%"'];
$s8 = ['ref' => 'S8', 'publisher' => 'Youlyou', 'url' => 'https://www.youlyou.com/52557', 'published_on' => '2026-09-12', 'read_on' => $read, 'origin' => 'youlyou-52557',
    'figure' => 'Mohamed El-Bostany, head of the Real Estate Developers Association: "رسوم التنازل كانت في السابق تتراوح بين 3% و5%، قبل أن يتم إلغاؤها باعتبارها غير قانونية"; government charge "نحو 1%"; some developers "لا يتجاوز 30 أو 40 ألف جنيه للوحدة"; has not heard of 15%'];
$s9 = ['ref' => 'S9', 'publisher' => 'Al Nahar', 'url' => 'https://www.alnaharegypt.com/1196578', 'published_on' => '2026-09-20', 'read_on' => $read, 'origin' => 'alnahar-1196578',
    'figure' => 'A member of parliament\'s question to the housing minister: "تصل في بعض الحالات إلى 15% من قيمة الوحدة" (its 1-5% is the study, same origin as S1)'];
$s10 = ['ref' => 'S10', 'publisher' => 'Eltaameer', 'url' => 'https://eltaameer.com/archives/384583', 'published_on' => '2025-08-06', 'read_on' => $read, 'origin' => 'alarabiya-2025-08-06', 'counted' => false,
    'figure' => 'Lawyers quoted: paying more than 75% blocks an automatic cancellation (Court of Cassation; Commercial Law art. 105); a court-ordered cancellation means a full refund "دون خصومات"'];
$s11 = ['ref' => 'S11', 'publisher' => 'Amwal Al Ghad', 'url' => 'https://amwalalghad.com/2025/08/06/%D9%81%D8%B3%D8%AE-%D8%A7%D9%84%D8%AA%D8%B9%D8%A7%D9%82%D8%AF-%D8%A7%D9%84%D8%B9%D9%82%D8%A7%D8%B1%D9%8A-%D9%81%D9%8A-%D9%85%D8%B5%D8%B1-%D8%AC%D8%AF%D9%84-%D9%82%D8%A7%D9%86%D9%88%D9%86%D9%8A/', 'published_on' => '2025-08-06', 'read_on' => $read, 'origin' => 'alarabiya-2025-08-06', 'counted' => false,
    'figure' => 'Same origin as S10: "دون خصم أي مصاريف إدارية أو نسبة 10%" on a court-ordered cancellation'];
$s12 = ['ref' => 'S12', 'publisher' => 'Bloom Gate', 'url' => 'https://bloom-gate.com/%D8%A3%D8%B3%D9%88%D8%A7%D9%82/%D9%88%D8%AF%D9%8A%D8%B9%D8%A9-%D8%A7%D9%84%D8%B5%D9%8A%D8%A7%D9%86%D8%A9-%D8%A3%D8%B2%D9%85%D8%A9-%D9%81%D9%8A-%D8%B3%D9%88%D9%82-%D8%A7%D9%84%D8%B9%D9%82%D8%A7%D8%B1%D8%A7%D8%AA-%D8%A7%D9%84%D9%85/', 'published_on' => '2026-10-28', 'read_on' => $read, 'origin' => 'bloomgate-maintenance',
    'figure' => 'Hesham Shoukry, Real Estate Export Council: "بعض الشركات تحصل ودائع صيانة تتراوح بين 8% و10%"; deposits "تصل عادة إلى 10% من قيمة الوحدة"'];
$s13 = ['ref' => 'S13', 'publisher' => 'Daily News Egypt', 'url' => 'https://www.dailynewsegypt.com/2026/10/31/cbe-rate-hike-aims-to-sustain-disinflation-ensure-2027-inflation-target-is-met/', 'published_on' => '2026-10-31', 'read_on' => $read, 'origin' => 'dne-cbe-2026-10',
    'figure' => 'Central Bank MPC of Thursday 2026-10-29: "bringing the overnight deposit rate to 20%, the overnight lending rate to 21%"'];
$s14 = ['ref' => 'S14', 'publisher' => 'Enterprise', 'url' => null, 'published_on' => '2026-11-01', 'read_on' => $read, 'origin' => 'enterprise-cbe-2026-11', 'counted' => false,
    'figure' => 'Headline seen in search results only, page not read: "CBE hikes for the first time since March 2024, raising rates 100 bps"'];
$s15 = ['ref' => 'S15', 'publisher' => 'Elwatan', 'url' => 'https://www.elwatannews.com/news/details/8222276', 'published_on' => '2026-02-10', 'read_on' => $read, 'origin' => 'elwatan-8222276', 'counted' => false,
    'figure' => 'Government social housing (not the private market, reference only): late fee "1.5 إلى 2.5 % شهريا"; allocation cancelled after two missed installments'];

return [
    'cancellation' => [
        'checked_on' => $read,
        'note' => 'Client 2026-11-25: 15% of the total unit price, one value (J2: the total price). Research: 10% of the total price has two independent sources (S1, S4, S5); 15%, 20% and 30% only the study (S1); every readable source uses the total price.',
        'confirmed_at' => $client,
        'sources' => [$s1, $s4, $s5, $s10, $s11],
    ],
    'refund' => [
        'checked_on' => $read,
        'note' => 'Client 2026-11-25: one payment (J4). Research: one readable source (S6), refunds in installments over a lengthy period, no length given.',
        'confirmed_at' => $client,
        'sources' => [$s6, $s10],
    ],
    'late_fee' => [
        'checked_on' => $read,
        'note' => 'Client 2026-11-25: 1% of each late installment, charged once, whatever the installment period (monthly, quarterly or yearly); the optional line is on (J5). Applied on both exits (Michael 2026-11-25). Research: no readable private-market figure; S15 is government housing, reference only.',
        'confirmed_at' => $client,
        'sources' => [$s15],
    ],
    'transfer_fee' => [
        'checked_on' => $read,
        'note' => 'Client 2026-11-25: a fixed EGP 50,000 by default; most developers charge a fixed amount. A seller\'s own figure in step 3 (a percentage or an amount) replaces it. Research: 1-5% of the unit value has two independent sources (S2, S8); up to 15% (S7, S9) is disputed by S8; a flat EGP 30,000-40,000 in S8.',
        'confirmed_at' => $client,
        'sources' => [$s2, $s8, $s7, $s9],
    ],
    'maintenance_deposit' => [
        'checked_on' => $read,
        'note' => 'Client 2026-11-25: it moves to the buyer with the transfer; shown as an information note only. Research: 8-10% of the unit price (S3, S12); shown publicly as "usually 8% to 10% of the unit price, moves to the buyer with the transfer", confirmed (Michael, PLAN K4).',
        'confirmed_at' => $client,
        'sources' => [$s3, $s12],
    ],
    'discount_rate' => [
        'checked_on' => $read,
        'note' => 'Used only if the present-value line is switched on (off, decided 2026-11-17; J7 picks this rate).',
        'sources' => [$s13, $s14],
    ],
];

<?php

/*
| Placeholder texts for the public pages (phase 2). Home and the footer use the copy on the final board
| (docs/design, sections 06.1 and 06.5); every other page is a short placeholder until the client's text arrives
| (agreement clause 7). All rows start as placeholders; the seeder adds missing keys only.
| Voice (board 02): headlines and calls to action in light Egyptian Arabic; legal text in Modern Standard Arabic.
|
| [key, group, ar, en, note]
*/

$p = 'نص مؤقت بانتظار نص العميل.';
$pe = 'Placeholder until the client\'s text arrives.';

return [
    // Footer and contact facts
    ['footer.about', 'footer',
        'سوق مصري لنقل عقود الوحدات بالتقسيط. المشتري ياخد الوحدة بسعر التعاقد الأصلي، والمالك يسترد اللي دفعه.',
        'An Egyptian marketplace for transferring installment property contracts. Buyers take the unit at the original contract price; owners recover what they paid.',
        'Footer, under the wordmark'],
    ['footer.email', 'footer', 'hello@loktaeg.com', 'hello@loktaeg.com',
        'Contact e-mail shown in the footer and on the contact page: a fact the client must confirm (PLAN 6 row 15)'],
    ['footer.city', 'footer', 'القاهرة، مصر', 'Cairo, Egypt', 'Footer copyright line'],

    // Home: hero
    ['home.hero.title_1', 'home', 'اشترِ بسعر', 'Buy at the', 'Hero headline, line 1'],
    ['home.hero.title_2', 'home', 'يوم التعاقد،', 'contract-day price,', 'Hero headline, line 2'],
    ['home.hero.title_3', 'home', 'مش بسعر النهارده.', 'not today\'s.', 'Hero headline, line 3 (light weight)'],
    ['home.hero.lead', 'home',
        'شقق ومكاتب ومحلات وأراضٍ بالتقسيط، بيتنازل عنها أصحابها بسعر العقد الأصلي. تدفع للمالك اللي دفعه، وتكمّل الأقساط مكانه.',
        'Apartments, offices, shops and land on installments, transferred by their owners at the original contract price. You pay the owner what they paid, then continue their installments.',
        'Hero intro paragraph'],
    ['home.hero.cta_primary', 'home', 'شوف الفرص المتاحة', 'Browse opportunities', 'Hero primary button'],
    ['home.hero.cta_secondary', 'home', 'عندك عقد؟ اعرضه واسترد اللي دفعته', 'Own a contract? Recover what you paid', 'Hero secondary link'],
    ['home.hero.example_note', 'home', 'مثال توضيحي، مش وحدة معروضة', 'Illustrative example, not a listed unit', 'Label on the hero figure until real units exist'],
    ['home.hero.contract_label', 'home', 'سعر التعاقد', 'Contract price', 'Hero figure label'],
    ['home.hero.find_label', 'home', 'لقطتك', 'Your find', 'Hero figure label (gold)'],
    ['home.hero.today_label', 'home', 'سعر وحدة مماثلة النهارده', 'Similar unit today', 'Hero figure label'],
    ['home.hero.photo_caption', 'home', 'صورة مؤقتة · هيكل خرساني، ضوء الظهيرة', 'Temporary image · concrete frame, midday light', 'Caption of the hero photo placeholder (photography brief PH-01)'],

    // Home: how we review
    ['home.trust.title', 'home',
        'كل وحدة بتتراجع بياناتها ومستنداتها قبل النشر. والموثّقة عليها ختمنا.',
        'Every unit\'s data and documents are checked before it goes live. Verified units carry our stamp.',
        'Review section title'],
    ['home.trust.body', 'home',
        'معماري قضى أكثر من 20 سنة في مواقع الإنشاء، ومحامٍ تحوّل لمستثمر عقاري. ده أسلوب شغلنا: كل واحد بيراجع اللي يفهم فيه، وختم «موثّقة» بيتحط بس لما نكون متأكدين.',
        'An architect with more than 20 years on construction sites, and a lawyer turned property investor. That is how we work: each of us checks what we know, and the «Verified» stamp goes on only when we are sure.',
        'Review section paragraph: the client\'s own claims, to confirm'],
    ['home.trust.partner_architect', 'home', 'الشريك المعماري', 'Architect partner', 'Caption under the partner portrait'],
    ['home.trust.partner_lawyer', 'home', 'الشريك المحامي', 'Lawyer partner', 'Caption under the partner portrait'],
    ['home.trust.col_item', 'home', 'بند', 'Item', 'Review table header'],
    ['home.trust.col_how', 'home', 'إزاي بنشتغل', 'How we work', 'Review table header'],
    ['home.trust.col_what', 'home', 'إيه اللي بيحصل', 'What happens', 'Review table header'],
    ['home.trust.step1_title', 'home', 'مراجعة البيانات والمستندات', 'Data and document check', 'Review table row 01'],
    ['home.trust.step1_sub', 'home', 'بإشراف المحامي الشريك', 'Led by our lawyer partner', 'Review table row 01'],
    ['home.trust.step1_text', 'home', 'قبل نشر أي وحدة نراجع العقد وجدول الأقساط وإيصالات السداد، ونتأكد إن البيانات منطقية ومتسقة.', 'Before any unit is listed, we check its contract, installment schedule and payment receipts for consistency.', 'Review table row 01'],
    ['home.trust.step2_title', 'home', 'معاينة هندسية', 'Architect site inspection', 'Review table row 02'],
    ['home.trust.step2_sub', 'home', 'عند الحاجة · المعماري الشريك', 'When needed', 'Review table row 02'],
    ['home.trust.step2_text', 'home', 'في بعض الوحدات بيزور المعماري الموقع للتأكد من الوحدة وحالتها ومطابقتها للعقد.', 'For some units, our architect visits the site to confirm the unit, its condition, and that it matches the contract.', 'Review table row 02'],
    ['home.trust.step3_title', 'home', 'خلال 48 ساعة', 'Within 48 hours', 'Review table row 03'],
    ['home.trust.step3_sub', 'home', 'من رفع المستندات', 'From document upload', 'Review table row 03'],
    ['home.trust.step3_text', 'home', 'يُنشر الإعلان أو نرسل لك الملاحظات بالبريد الإلكتروني، وكل خطوة توصلك على الإيميل.', 'The listing goes live or you receive our notes. Every update reaches you by e-mail.', 'Review table row 03'],
    ['home.trust.verified_title', 'home', 'ختم «موثّقة»', 'Verified stamp', 'Review table, verified row'],
    ['home.trust.verified_sub', 'home', 'مش على كل وحدة', 'Not on every unit', 'Review table, verified row'],
    ['home.trust.stamp', 'home', 'موثّقة', 'Verified', 'Word inside the round verified stamp'],
    ['home.trust.verified_text', 'home', 'بيظهر بس لما المستندات تكون متسقة بالكامل، أو لما نكون عاينّا الوحدة بنفسنا.', 'Given only when the documents are fully consistent, or when we have inspected the unit ourselves.', 'Review table, verified row'],

    // Home: seller band (paper)
    ['home.seller.kicker', 'home', 'للمالك', 'For owners', 'Seller band label'],
    ['home.seller.title', 'home', 'عندك عقد ومش قادر تكمّل الأقساط؟', 'Own a contract you can\'t keep paying?', 'Seller band title'],
    ['home.seller.body', 'home',
        'بِع عقدك واسترد اللي دفعته. إحنا بنراجع، بنعرض الوحدة على مستثمرين جادين، وبنتابع التنازل مع المطوّر لحد الآخر.',
        'Sell your contract and recover what you paid. We review it, show the unit to serious investors, and follow the transfer with the developer to the end.',
        'Seller band paragraph'],
    ['home.seller.cta', 'home', 'ابدأ ببيانات العقد', 'Start with your contract', 'Seller band button'],
    ['home.seller.note', 'home', 'تقدر توقف وترجع تكمّل في أي وقت', 'You can stop and come back at any time', 'Next to the seller band button'],
    ['home.seller.step1', 'home', 'بيانات العقد', 'Contract', 'Seller step 01 title'],
    ['home.seller.step1_text', 'home', 'المطوّر والكمبوند ونوع الوحدة وسنة التعاقد.', 'Developer, compound, unit type and contract date.', 'Seller step 01'],
    ['home.seller.step2', 'home', 'بيانات الوحدة', 'Unit', 'Seller step 02 title'],
    ['home.seller.step2_text', 'home', 'المساحة والدور والتشطيب والاستلام والواجهة.', 'Size, floor, finishing, delivery and orientation.', 'Seller step 02'],
    ['home.seller.step3', 'home', 'السعر والأقساط', 'Price and installments', 'Seller step 03 title'],
    ['home.seller.step3_text', 'home', 'اللي دفعته والباقي، وتقرير الخروج بيتحسب وانت بتكتب.', 'What you paid and what remains; the exit report updates as you type.', 'Seller step 03'],
    ['home.seller.step4', 'home', 'المستندات', 'Documents', 'Seller step 04 title'],
    ['home.seller.step4_text', 'home', 'العقد وجدول الأقساط والبطاقة. محدش بيشوفها غير فريق المراجعة.', 'Contract, installment schedule and ID. Only our review team sees them.', 'Seller step 04'],
    ['home.seller.step5', 'home', 'الصور والوصف', 'Photos and description', 'Seller step 05 title'],
    ['home.seller.step5_text', 'home', 'من صورة لعشرة، ووصف قصير للوحدة.', 'One to ten photos and a short description.', 'Seller step 05'],
    ['home.seller.step6', 'home', 'المراجعة والإرسال', 'Review and submit', 'Seller step 06 title'],
    ['home.seller.step6_text', 'home', 'راجع كل حاجة وابعتها. بنرد عليك خلال 48 ساعة بالإيميل.', 'Check everything and send it. We reply within 48 hours by e-mail.', 'Seller step 06'],

    // Home: picks
    ['home.picks.title', 'home', 'لقطات الأسبوع', 'This week\'s finds', 'Picks section title'],
    ['home.picks.subtitle', 'home', 'أكبر فرق بين سعر التعاقد وسعر النهارده، بعد المراجعة.', 'The biggest gaps between the contract price and today\'s price, after review.', 'Picks section subtitle'],
    ['home.picks.all', 'home', 'كل الوحدات المتاحة', 'All available units', 'Link to the opportunities page'],
    ['home.picks.empty', 'home', 'أول الوحدات تحت المراجعة دلوقتي، وهتظهر هنا أول ما تتنشر.', 'The first units are under review now and will appear here once published.', 'Shown while no unit is published'],
    ['home.picks.recent', 'home', 'وصل حديثًا', 'Just listed', 'Recent units column title'],

    // Content pages: title, intro, body (paragraphs separated by a blank line)
    ['about.title', 'about', 'مين إحنا', 'About us', 'About page title'],
    ['about.intro', 'about', 'لقطة سوق مصري لنقل عقود الوحدات بالتقسيط.', 'Lokta is an Egyptian marketplace for transferring installment property contracts.', 'About page intro'],
    ['about.body', 'about', $p, $pe, 'About page body: the client\'s story (PLAN 6 row 3)'],

    ['how.title', 'how', 'إزاي بنشتغل', 'How it works', 'How it works page title'],
    ['how.intro', 'how', 'من أول طلب البيع لحد التنازل عند المطوّر.', 'From the first sale request to the transfer at the developer.', 'How it works intro'],
    ['how.seller_title', 'how', 'لو بتبيع', 'If you are selling', 'How it works, seller part'],
    ['how.seller_body', 'how', "بتملا بيانات العقد والوحدة والأقساط وترفع المستندات. بنراجع خلال 48 ساعة، وبعد النشر بنعرض الوحدة على المشترين. عمولة لقطة على البائع 0%.\n\n".$p, "You fill in the contract, unit and installments and upload the documents. We review within 48 hours, then show the unit to buyers. Lokta charges the seller 0%.\n\n".$pe, 'How it works, seller part'],
    ['how.buyer_title', 'how', 'لو بتشتري', 'If you are buying', 'How it works, buyer part'],
    ['how.buyer_body', 'how', "بتدفع للمالك اللي دفعه، مش أكتر، وتكمّل الأقساط الباقية بنفس جدول المطوّر. عمولة لقطة 1% من سعر التعاقد، وبتتدفع بس لما التنازل يتم.\n\n".$p, "You pay the owner what they paid, never more, and continue the remaining installments on the developer's schedule. Lokta's fee is 1% of the contract price, paid only when the transfer completes.\n\n".$pe, 'How it works, buyer part'],

    ['buyers.title', 'buyers', 'للمشترين', 'For buyers', 'For buyers page title'],
    ['buyers.intro', 'buyers', 'اشترِ بسعر يوم التعاقد، مش بسعر النهارده.', 'Buy at the contract-day price, not today\'s.', 'For buyers intro'],
    ['buyers.body', 'buyers', "سجّل المبلغ اللي معاك كاش وقيمة القسط اللي تقدر عليها، وفريقنا بيرشّح لك الوحدات المناسبة.\n\n".$p, "Register the cash you have and the installment you can afford, and our team suggests the units that fit.\n\n".$pe, 'For buyers body (PLAN 2.3)'],

    ['faq.title', 'faq', 'الأسئلة الشائعة', 'Frequently asked questions', 'FAQ page title'],
    ['faq.intro', 'faq', 'التنازل، الأقساط، والرسوم.', 'Transfers, installments and fees.', 'FAQ intro'],
    ['faq.q1', 'faq', 'لقطة بتاخد عمولة كام؟', 'What does Lokta charge?', 'FAQ 1'],
    ['faq.a1', 'faq', 'البائع: 0%. المشتري: 1% من سعر التعاقد، بتتدفع بس لما التنازل يتم.', 'Sellers pay 0%. Buyers pay 1% of the contract price, only when the transfer completes.', 'FAQ 1 (decided fees)'],
    ['faq.q2', 'faq', 'يعني إيه مفيش أوفر؟', 'What does "no over" mean?', 'FAQ 2'],
    ['faq.a2', 'faq', 'أقصى مبلغ يطلبه المالك هو اللي دفعه فعلًا. يقدر يقبل أقل علشان يبيع أسرع، لكن مش أكتر.', 'The most an owner can ask is what they actually paid. They may accept less to sell faster, never more.', 'FAQ 2 (decided rule)'],
    ['faq.q3', 'faq', 'مين بيشوف مستندات الوحدة؟', 'Who sees the unit\'s documents?', 'FAQ 3'],
    ['faq.a3', 'faq', 'فريق المراجعة في لقطة بس. المشترين مش بيشوفوا المستندات ولا رقم الوحدة ولا بيانات المالك.', 'Only Lokta\'s review team. Buyers never see the documents, the unit number or the owner\'s details.', 'FAQ 3 (decided privacy rule)'],
    ['faq.q4', 'faq', 'يعني إيه وحدة «موثّقة»؟', 'What does «Verified» mean?', 'FAQ 4'],
    ['faq.a4', 'faq', 'كل وحدة بتتراجع قبل النشر. ختم «موثّقة» بيتحط بس لما المستندات تكون متسقة بالكامل أو لما نكون عاينّا الوحدة بنفسنا.', 'Every unit is checked before it is published. The «Verified» stamp is given only when the documents are fully consistent or when we have inspected the unit ourselves.', 'FAQ 4 (decided meaning)'],

    ['contact.title', 'contact', 'تواصل معنا', 'Contact us', 'Contact page title'],
    ['contact.intro', 'contact', 'ابعتلنا على الإيميل وبنرد عليك في أقرب وقت.', 'Write to us by e-mail and we will reply as soon as we can.', 'Contact page intro; the contact form comes in phase 5'],
    ['contact.body', 'contact', $p, $pe, 'Contact facts: phone, address, hours (PLAN 6 row 15)'],

    ['developers.title', 'developers', 'للمطوّرين', 'For developers', 'Developers page title'],
    ['developers.intro', 'developers', 'نص مؤقت: مقدمة صفحة المطوّرين ونقاط الشراكة يكتبها العميل.', 'Placeholder: the developers page intro and partnership points are written by the client.', 'Developers page intro (PLAN 6 row 12)'],
    ['developers.body', 'developers', $p, $pe, 'Partnership points (PLAN 6 row 12)'],
    ['compounds.title', 'compounds', 'الكمبوندات', 'Compounds', 'Compounds index title'],
    ['compounds.intro', 'compounds', 'نص مؤقت: مقدمة صفحة الكمبوندات يكتبها العميل.', 'Placeholder: the compounds page intro is written by the client.', 'Compounds index intro (PLAN 6 row 17)'],
    ['opportunities.title', 'opportunities', 'الفرص المتاحة', 'Opportunities', 'Opportunities page title'],
    ['opportunities.intro', 'opportunities', 'الأرقام هنا هي اللي هتدفعها فعلًا: اللي دفعه المالك، والأقساط اللي باقية للمطوّر.', 'The figures here are what you actually pay: what the owner paid, and the installments left for the developer.', 'Opportunities intro (board 06.2)'],

    ['legal.privacy.title', 'legal', 'سياسة الخصوصية', 'Privacy policy', 'Privacy page title'],
    ['legal.privacy.body', 'legal', 'نص مؤقت: سياسة الخصوصية يكتبها العميل (البند السابع من الاتفاق).', 'Placeholder: the privacy policy is written by the client (agreement, clause 7).', 'Privacy policy text (PLAN 6 row 6)'],
    ['legal.terms.title', 'legal', 'الشروط والأحكام', 'Terms and conditions', 'Terms page title'],
    ['legal.terms.body', 'legal', 'نص مؤقت: الشروط والأحكام يكتبها العميل (البند السابع من الاتفاق).', 'Placeholder: the terms and conditions are written by the client (agreement, clause 7).', 'Terms text (PLAN 6 row 7)'],
];

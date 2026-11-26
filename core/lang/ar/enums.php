<?php

/*
| Arabic labels for every code stored in the database (App\Enums). Placeholder wording until the client reviews
| it (PLAN 6, row 20).
*/

return [
    'UnitState' => [
        'draft' => 'مسودة',
        'submitted' => 'قيد المراجعة',
        'changes_requested' => 'مطلوب تعديلات',
        'rejected' => 'مرفوضة',
        'published' => 'منشورة',
        'reserved' => 'محجوزة',
        'sold' => 'تم البيع',
        'withdrawn' => 'مسحوبة',
    ],
    'MainType' => [
        'residential' => 'سكني',
        'commercial' => 'تجاري',
        'administrative' => 'إداري',
        'land' => 'أراضي',
    ],
    'SubType' => [
        'apartment' => 'شقة',
        'garden_apartment' => 'شقة أرضي بحديقة',
        'duplex' => 'دوبلكس',
        'penthouse' => 'بنتهاوس',
        'studio' => 'استوديو',
        'serviced_apartment' => 'شقة فندقية',
        'townhouse' => 'تاون هاوس',
        'twin_house' => 'توين هاوس',
        'quattro_villa' => 'كواترو فيلا',
        'i_villa' => 'آي فيلا',
        'standalone_villa' => 'فيلا مستقلة',
        'chalet' => 'شاليه',
        'cabin' => 'كابينة',
        'shop' => 'محل تجاري',
        'pharmacy' => 'صيدلية',
        'restaurant_cafe' => 'مطعم أو كافيه',
        'kiosk' => 'كشك',
        'showroom' => 'معرض (شوروم)',
        'warehouse' => 'مخزن',
        'office' => 'مكتب / وحدة إدارية',
        'clinic' => 'عيادة',
        'residential_land' => 'أرض سكنية',
        'commercial_land' => 'أرض تجارية',
        'administrative_land' => 'أرض إدارية',
        'industrial_land' => 'أرض صناعية',
    ],
    'Finishing' => [
        'core_shell' => 'على الطوب الأحمر',
        'semi_finished' => 'نصف تشطيب',
        'fully_finished' => 'تشطيب كامل',
        'finished_kitchen_acs' => 'تشطيب كامل بالمطبخ والتكييفات',
        'furnished' => 'مفروش',
    ],
    'DeliveryStatus' => [
        'ready' => 'جاهزة للاستلام',
        'under_construction' => 'تحت الإنشاء',
    ],
    'DeliveryPreference' => [
        'ready' => 'جاهزة للاستلام',
        'under_construction' => 'تحت الإنشاء',
        'either' => 'أي حالة',
    ],
    'OverdueSettlement' => [
        'self' => 'المالك يسدّدها قبل التنازل',
        'deduct' => 'تُخصم من المبلغ اللي يستلمه المالك',
    ],
    'InstallmentFrequency' => [
        'monthly' => 'شهري',
        'quarterly' => 'ربع سنوي',
        'semi_annual' => 'نصف سنوي',
        'annual' => 'سنوي',
    ],
    'DocumentType' => [
        'contract' => 'صورة العقد',
        'schedule' => 'جدول الأقساط',
        'national_id_front' => 'بطاقة الرقم القومي (الوجه)',
        'national_id_back' => 'بطاقة الرقم القومي (الظهر)',
        'receipt' => 'إيصالات السداد',
        'statement' => 'آخر كشف حساب من المطور',
    ],
    'DocumentStatus' => [
        'uploaded' => 'تم الاستلام، قيد المراجعة',
        'approved' => 'تمت المراجعة',
        'needs_replacement' => 'مطلوب نسخة أوضح',
    ],
    'ReviewOutcome' => [
        'changes_requested' => 'مطلوب تعديلات',
        'rejected' => 'مرفوضة',
        'published' => 'نشر',
        'published_verified' => 'نشر كوحدة موثقة',
    ],
    'DeclarationKind' => [
        'no_over' => 'بدون أوفر',
        'owner_not_broker' => 'أنا المالك ولست وسيطًا',
        'data_matches' => 'البيانات مطابقة للعقد والمستندات',
        'terms_privacy' => 'الموافقة على الشروط وسياسة الخصوصية',
    ],
    'PhotoKind' => [
        'photo' => 'صورة',
        'floor_plan' => 'مخطط الوحدة',
    ],
    'FeatureGroup' => [
        'unit' => 'الوحدة',
        'building' => 'المبنى',
        'compound' => 'المشروع',
        'view' => 'الإطلالة',
    ],
    'AdminRole' => [
        'admin' => 'مدير',
        'reviewer' => 'مراجع',
    ],
    'MailState' => [
        'pending' => 'في الانتظار',
        'sent' => 'تم الإرسال',
        'failed' => 'فشل، ستتم إعادة المحاولة',
        'dead' => 'فشل نهائي',
    ],
    'TextSource' => [
        'placeholder' => 'نص مؤقت',
        'ours' => 'كتبناه، بانتظار مراجعة العميل',
        'legal_review' => 'بانتظار المراجعة القانونية',
        'client' => 'نص العميل',
    ],
    'Readiness' => [
        'now' => 'جاهز دلوقتي',
        'within_3_months' => 'خلال شهر لـ3 شهور',
        'looking' => 'بتفرّج بس',
    ],
    'RequestState' => [
        'new' => 'جديد',
        'contacted' => 'تم التواصل',
        'matched' => 'اتفتحت صفقة',
        'closed' => 'مقفول',
    ],
];

<?php

/*
| Validation messages (Arabic) for the rules the public forms use. The API answers in the language of ?locale=.
*/

return [
    'accepted' => 'لازم توافق على :attribute.',
    'array' => 'قيمة :attribute غير صحيحة.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'distinct' => 'فيه قيمة مكررة في :attribute.',
    'email' => 'اكتب :attribute صحيح.',
    'exists' => 'قيمة :attribute غير موجودة.',
    'in' => 'اختيار :attribute غير صحيح.',
    'integer' => ':attribute لازم يكون رقم صحيح.',
    'numeric' => ':attribute لازم يكون رقم.',
    'not_regex' => ':attribute لازم يكون في سطر واحد من غير رموز مخفية.',
    'max' => [
        'numeric' => ':attribute لازم يكون :max أو أقل.',
        'string' => ':attribute لازم يكون :max حرف أو أقل.',
        'array' => 'اختار :max أو أقل في :attribute.',
    ],
    'min' => [
        'numeric' => ':attribute لازم يكون :min أو أكثر.',
        'string' => ':attribute لازم يكون :min حروف على الأقل.',
        'array' => 'اختار :min على الأقل في :attribute.',
    ],
    'prohibited' => 'الحقل ده لازم يفضل فاضي.',
    'required' => ':attribute مطلوب.',
    'string' => 'قيمة :attribute غير صحيحة.',
    'unique' => ':attribute ده مسجّل قبل كده.',

    'custom' => [
        'phone' => [
            'phone' => 'اكتب رقم موبايل مصري صحيح (مثلًا 01012345678).',
        ],
    ],

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الموبايل',
        'password' => 'كلمة المرور',
        'terms' => 'الشروط وسياسة الخصوصية',
        'cash_available' => 'المبلغ المتاح كاش',
        'max_installment' => 'أقصى قسط مريح',
        'installment_frequency' => 'دورية القسط',
        'main_types' => 'أنواع الوحدات',
        'areas' => 'المناطق',
        'compounds' => 'الكمبوندات',
        'target_price' => 'سعر التعاقد المستهدف',
        'min_bedrooms' => 'أقل عدد غرف',
        'delivery_pref' => 'الاستلام',
        'code' => 'كود الوحدة',
    ],
];

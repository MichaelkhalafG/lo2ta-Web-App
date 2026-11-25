<?php

/*
| Validation messages (English) for the rules the public forms use. The API answers in the language of ?locale=.
*/

return [
    'accepted' => 'Please accept the :attribute.',
    'array' => 'The :attribute is not valid.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'distinct' => 'The :attribute has a duplicate value.',
    'email' => 'Enter a valid :attribute.',
    'exists' => 'The selected :attribute does not exist.',
    'in' => 'The selected :attribute is not valid.',
    'integer' => 'The :attribute must be a whole number.',
    'numeric' => 'The :attribute must be a number.',
    'not_regex' => 'The :attribute must be one line without hidden characters.',
    'max' => [
        'numeric' => 'The :attribute must be :max or less.',
        'string' => 'The :attribute must be :max characters or fewer.',
        'array' => 'Choose :max or fewer :attribute.',
    ],
    'min' => [
        'numeric' => 'The :attribute must be at least :min.',
        'string' => 'The :attribute must be at least :min characters.',
        'array' => 'Choose at least :min :attribute.',
    ],
    'prohibited' => 'This field must stay empty.',
    'required' => 'The :attribute is required.',
    'string' => 'The :attribute is not valid.',
    'unique' => 'This :attribute is already registered.',

    'custom' => [
        'phone' => [
            'phone' => 'Enter a valid Egyptian mobile number (for example 01012345678).',
        ],
    ],

    'attributes' => [
        'name' => 'name',
        'email' => 'e-mail',
        'phone' => 'mobile number',
        'password' => 'password',
        'terms' => 'terms and privacy policy',
        'cash_available' => 'cash available',
        'max_installment' => 'comfortable installment',
        'installment_frequency' => 'installment frequency',
        'main_types' => 'unit types',
        'areas' => 'areas',
        'compounds' => 'compounds',
        'target_price' => 'target contract price',
        'min_bedrooms' => 'minimum bedrooms',
        'delivery_pref' => 'delivery',
        'code' => 'unit code',
        'total_price' => 'total contract price',
        'paid_amount' => 'amount paid',
        'years_paid' => 'years paid',
        'overdue_amount' => 'overdue amount',
        'developer' => 'developer',
        'contract_price' => 'contract price',
        'price_today' => "developer's price today",
    ],
];

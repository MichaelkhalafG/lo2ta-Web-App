<?php

/*
| English labels for every code stored in the database (App\Enums). Placeholder wording until the client reviews
| it (PLAN 6, row 20).
*/

return [
    'UnitState' => [
        'draft' => 'Draft',
        'submitted' => 'In review',
        'changes_requested' => 'Changes requested',
        'rejected' => 'Rejected',
        'published' => 'Published',
        'reserved' => 'Reserved',
        'sold' => 'Sold',
        'withdrawn' => 'Withdrawn',
    ],
    'MainType' => [
        'residential' => 'Residential',
        'commercial' => 'Commercial',
        'administrative' => 'Administrative',
        'land' => 'Land',
    ],
    'SubType' => [
        'apartment' => 'Apartment',
        'garden_apartment' => 'Ground-floor apartment with garden',
        'duplex' => 'Duplex',
        'penthouse' => 'Penthouse',
        'studio' => 'Studio',
        'serviced_apartment' => 'Serviced (hotel) apartment',
        'townhouse' => 'Townhouse',
        'twin_house' => 'Twin house',
        'quattro_villa' => 'Quattro villa',
        'i_villa' => 'i-Villa',
        'standalone_villa' => 'Standalone villa',
        'chalet' => 'Chalet',
        'cabin' => 'Cabin',
        'shop' => 'Shop',
        'pharmacy' => 'Pharmacy',
        'restaurant_cafe' => 'Restaurant or café',
        'kiosk' => 'Kiosk',
        'showroom' => 'Showroom',
        'warehouse' => 'Warehouse',
        'office' => 'Office / administrative unit',
        'clinic' => 'Clinic',
        'residential_land' => 'Residential land plot',
        'commercial_land' => 'Commercial land plot',
        'administrative_land' => 'Administrative land plot',
        'industrial_land' => 'Industrial land plot',
    ],
    'Finishing' => [
        'core_shell' => 'Core & shell',
        'semi_finished' => 'Semi-finished',
        'fully_finished' => 'Fully finished',
        'finished_kitchen_acs' => 'Fully finished with kitchen and ACs',
        'furnished' => 'Furnished',
    ],
    'DeliveryStatus' => [
        'ready' => 'Ready',
        'under_construction' => 'Under construction',
    ],
    'InstallmentFrequency' => [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'semi_annual' => 'Semi-annual',
        'annual' => 'Annual',
    ],
    'DocumentType' => [
        'contract' => 'Contract copy',
        'schedule' => 'Installment schedule',
        'national_id_front' => 'National ID (front)',
        'national_id_back' => 'National ID (back)',
        'receipt' => 'Payment receipts',
        'statement' => 'Latest developer statement',
    ],
    'DocumentStatus' => [
        'uploaded' => 'Received, in review',
        'approved' => 'Reviewed',
        'needs_replacement' => 'Needs a clearer copy',
    ],
    'ReviewOutcome' => [
        'changes_requested' => 'Changes requested',
        'rejected' => 'Rejected',
        'published' => 'Publish',
        'published_verified' => 'Publish as verified',
    ],
    'DeclarationKind' => [
        'no_over' => 'No over price',
        'owner_not_broker' => 'I am the owner, not a broker',
        'data_matches' => 'The data matches my contract and documents',
        'terms_privacy' => 'I accept the terms and privacy policy',
    ],
    'PhotoKind' => [
        'photo' => 'Photo',
        'floor_plan' => 'Floor plan',
    ],
    'FeatureGroup' => [
        'unit' => 'Unit',
        'building' => 'Building',
        'compound' => 'Project',
        'view' => 'View',
    ],
    'AdminRole' => [
        'admin' => 'Admin',
        'reviewer' => 'Reviewer',
    ],
    'MailState' => [
        'pending' => 'Pending',
        'sent' => 'Sent',
        'failed' => 'Failed, will retry',
        'dead' => 'Failed for good',
    ],
    'TextSource' => [
        'placeholder' => 'Placeholder',
        'client' => 'Client text',
    ],
];

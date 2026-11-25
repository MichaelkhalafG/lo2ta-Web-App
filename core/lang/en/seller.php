<?php

// The seller wizard's server messages (PLAN 2.2). Placeholder wording for the client to confirm (PLAN 6 row 23).
return [
    'duplicate' => 'This contract is already listed with us or under review. If you think this is a mistake, contact us.',
    'throttled' => 'Too many attempts. Try again in :minutes minutes.',
    'too_many_drafts' => 'You have :max open drafts. Finish or delete one first.',
    'not_editable' => 'This unit cannot be changed in its current state.',
    'not_deletable' => 'Only a draft that was never submitted can be deleted.',
    'not_withdrawable' => 'This unit cannot be withdrawn in its current state.',
    'email_not_verified' => 'Confirm your e-mail address first to submit the unit.',
    'rules' => [
        'step' => 'This step does not exist.',
        'required' => 'This field is required.',
        'pair_one' => 'Choose from the list or type it, not both.',
        'transfer_fee_one' => 'Give the transfer fee as a percentage or as an amount, not both.',
        'compound_developer' => 'This project does not belong to the developer you chose.',
        'sub_type_main' => 'The sub-type does not belong to the main type you chose.',
        'date_future' => 'This date is in the future.',
        'step1_first' => 'Complete the contract details first.',
        'delivery_before_contract' => 'The delivery year is before the contract year.',
        'feature_scope' => 'A feature does not apply to this kind of unit.',
        'paid_over_total' => 'The amount paid is more than the total contract price.',
        'installment_over_remaining' => 'The installment is more than the remaining amount.',
        'due_date_past' => 'The next installment date must be in the future.',
        'over_remaining' => 'The amount is more than what remains on the unit.',
        'lowest_not_below_paid' => 'The lowest amount you accept must be less than what you paid.',
        'description_length' => 'The description must be :min to :max characters.',
        'contact_details' => 'Phone numbers, e-mail addresses and links are not allowed in the description.',
        'photos_count' => 'Upload :min to :max photos.',
        'locked' => 'This field is locked after publishing. To change it, withdraw the unit and list it again.',
        'photo_order' => 'The photo order must list every photo.',
    ],
];

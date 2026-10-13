<?php

return [
    'common' => [
        'states' => [
            'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar',
            'Chandigarh', 'Chhattisgarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Goa',
            'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir', 'Jharkhand', 'Karnataka',
            'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya',
            'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
            'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
        ],
    ],

    'prime' => [
        'age_groups' => [
            ['value' => '18-24', 'label' => '18–24 years'],
            ['value' => '25+', 'label' => '25+ years'],
        ],
        'occupations' => [
            ['value' => 'Business Owner', 'label' => 'Business Owner'],
            ['value' => 'Entrepreneur', 'label' => 'Entrepreneur'],
        ],
        'business_experience' => [
            ['id' => 'business_yes', 'value' => 'yes', 'label' => 'Yes', 'icon' => '✓', 'description' => 'I have business or management experience'],
            ['id' => 'business_no', 'value' => 'no', 'label' => 'No', 'icon' => '—', 'description' => 'This would be my first business venture'],
        ],
        'property_status' => [
            ['id' => 'property_yes', 'value' => 'yes', 'label' => 'Yes', 'icon' => '✓', 'description' => 'I already have a property'],
            ['id' => 'property_no', 'value' => 'no', 'label' => 'No', 'icon' => '—', 'description' => 'I do not have a property yet'],
            ['id' => 'property_rent', 'value' => 'looking_to_rent', 'label' => 'Looking to rent', 'icon' => '⌂', 'description' => 'I am actively exploring options'],
        ],
        'road_facing' => [
            ['id' => 'road_yes', 'value' => 'yes', 'label' => 'Yes'],
            ['id' => 'road_no', 'value' => 'no', 'label' => 'No'],
        ],
        'timelines' => [
            ['value' => 'Immediately (within 30 days)', 'label' => 'Immediately (within 30 days)'],
            ['value' => 'Within 1-3 months', 'label' => 'Within 1–3 months'],
            ['value' => 'Within 3-6 months', 'label' => 'Within 3–6 months'],
            ['value' => 'Within 6-12 months', 'label' => 'Within 6–12 months'],
            ['value' => 'Just exploring the opportunity', 'label' => 'Just exploring the opportunity'],
        ],
        'decision_maker' => [
            ['id' => 'decision_yes', 'value' => 'yes', 'label' => 'Yes'],
            ['id' => 'decision_no', 'value' => 'no', 'label' => 'No'],
            ['id' => 'decision_joint', 'value' => 'joint', 'label' => 'Joint decision with family / business partner'],
        ],
        'contact_methods' => [
            ['id' => 'contact_phone', 'value' => 'Phone Call', 'label' => '☎ Phone Call'],
            ['id' => 'contact_whatsapp', 'value' => 'WhatsApp', 'label' => '◉ WhatsApp'],
            ['id' => 'contact_email', 'value' => 'Email', 'label' => '✉ Email'],
        ],
    ],
    'fofo' => [
        'hearabout' => ['Google-Search', 'Youtube', 'Facebook', 'Instagram', 'E-Mailing', 'Referral', 'Other'],
        'contacted' => ['Telephone', 'Mail', 'Whatsapp'],
        'wantstart' => [
            ['value' => 'Immediatly', 'label' => 'Immediately'],
            ['value' => '2 Months', 'label' => '2 Months'],
            ['value' => '3 Months', 'label' => '3 Months'],
        ],
        'yes_no' => ['NO', 'YES'],
        'spendfranchise' => ['Full Time', 'Part Time', 'Other'],
        'civillitigation' => ['yes', 'no'],
    ],
];

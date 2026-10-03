<?php

return [
    'title' => 'Settings',
    'subtitle' => 'Review plan usage, control what you see, and manage your shop preferences.',
    'tabs' => [
        'plan' => 'Plan & usage',
        'products' => 'Products',
        'visibility' => 'What I see',
        'team' => 'Team',
        'pos' => 'POS',
        'account' => 'Account',
    ],
    'plan' => [
        'title' => 'Plan & usage',
        'subtitle' => 'Current limits and usage for your shop.',
        'entries' => 'Entries',
        'current_usage' => 'Current usage',
        'remaining' => 'Remaining',
        'unlimited' => 'Unlimited plan',
        'images_title' => 'Product images',
        'images_hint' => 'Unique images stored for this shop.',
        'image_count' => 'Images used',
        'image_limit' => 'Image limit',
        'image_missing' => 'Missing files',
        'image_size' => 'Storage used',
        'limit_note' => 'Contact support if you need a higher limit.',
    ],
    'products' => [
        'title' => 'Products',
        'subtitle' => 'Choose when out-of-stock products should warn you or become inactive.',
        'warning_period' => 'Warning period',
        'deactivation_period' => 'Deactivation period',
        'months' => 'months',
        'warning_hint' => 'Show warnings after this many months without stock movement.',
        'deactivation_hint' => 'Automatically deactivate after this many months without stock movement.',
        'deactivation_after_warning' => 'The deactivation period must be greater than the warning period.',
        'save' => 'Save product settings',
    ],
    'visibility' => [
        'title' => 'What I see',
        'subtitle' => 'Choose which numbers are visible in your account.',
        'save' => 'Save visibility settings',
        'options' => [
            'show_bills_total_sales' => [
                'label' => 'Bills total sales',
                'hint' => 'Show the total sales summary on the bills page.',
            ],
            'show_bills_total_profit' => [
                'label' => 'Bills total profit',
                'hint' => 'Show the total profit summary on the bills page.',
            ],
            'show_bills_count' => [
                'label' => 'Bills count',
                'hint' => 'Show the bills count summary box.',
            ],
            'show_bill_total_value' => [
                'label' => 'Bill total value column',
                'hint' => 'Show the total value column inside the bills table.',
            ],
            'show_bill_profit_column' => [
                'label' => 'Bill profit column',
                'hint' => 'Show the profit column for each bill.',
            ],
            'show_dashboard_total_sales' => [
                'label' => 'Dashboard total sales',
                'hint' => 'Show today’s total sales on the dashboard and POS header.',
            ],
            'show_product_cost_price' => [
                'label' => 'Product cost price',
                'hint' => 'Show cost price on product-related pages.',
            ],
        ],
    ],
    'team' => [
        'title' => 'Team',
        'subtitle' => 'Create employee accounts and decide what each person can see and do.',
        'description' => 'Open the team page to manage permissions, visibility, passwords, and sign-outs for employee accounts.',
        'open' => 'Open team management',
        'owners_only' => 'Only owner accounts can manage employee accounts.',
    ],
    'pos' => [
        'title' => 'POS',
        'subtitle' => 'The POS layout customizer lives inside the sales point.',
        'description' => 'Open the dashboard and use the “Customize layout” button from the POS area to arrange cards and shortcuts.',
        'open' => 'Open sales point',
    ],
    'account' => [
        'title' => 'Account',
        'subtitle' => 'Update your profile information and password.',
        'open' => 'Open profile',
    ],
    'messages' => [
        'products_updated' => 'Product settings updated successfully.',
        'visibility_updated' => 'Visibility settings updated successfully.',
        'employee_visibility_updated' => 'Employee visibility updated successfully.',
        'image_limit_updated' => 'Image limit updated successfully.',
        'unauthorized' => 'Unauthorized action.',
    ],
];

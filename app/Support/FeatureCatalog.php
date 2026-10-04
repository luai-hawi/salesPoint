<?php

namespace App\Support;

class FeatureCatalog
{
    public const KEYS = [
        'installments',
        'sales_promotions',
        'financial_dashboard',
        'hr',
        'reports',
        'team_activity',
    ];

    public static function reportFeature(string $type): ?string
    {
        return match ($type) {
            'employee_payments' => 'hr',
            'employee_work' => 'team_activity',
            default => null,
        };
    }
}

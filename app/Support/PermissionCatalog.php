<?php

namespace App\Support;

/**
 * Single source of truth for every employee permission in the system.
 *
 * Permissions are stored on users.permissions as a JSON array of the keys below.
 * Existing keys must never be renamed or removed: saved employee accounts rely on them.
 */
class PermissionCatalog
{
    /**
     * Permission groups => keys. Order is the display order in the permission picker.
     * Each key maps to the keys it implies (granting "edit" always needs "view").
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function groups(): array
    {
        return [
            'products' => [
                'view_products' => [],
                'create_products' => ['view_products'],
                'edit_products' => ['view_products'],
                'delete_products' => ['view_products'],
            ],
            'sales' => [
                'view_bills' => [],
                'create_bills' => ['view_bills'],
                'edit_bills' => ['view_bills'],
                'delete_bills' => ['view_bills'],
            ],
            'customers' => [
                'view_customers' => [],
                'create_customers' => ['view_customers'],
                'edit_customers' => ['view_customers'],
                'delete_customers' => ['view_customers'],
                'manage_payments_receipts' => [],
            ],
            'purchasing' => [
                'view_suppliers' => [],
                'create_suppliers' => ['view_suppliers'],
                'edit_suppliers' => ['view_suppliers'],
                'delete_suppliers' => ['view_suppliers'],
                'view_purchase_bills' => [],
                'create_purchase_bills' => ['view_purchase_bills'],
                'edit_purchase_bills' => ['view_purchase_bills'],
                'delete_purchase_bills' => ['view_purchase_bills'],
            ],
            'finance' => [
                'view_expenses' => [],
                'create_expenses' => ['view_expenses'],
                'edit_expenses' => ['view_expenses'],
                'delete_expenses' => ['view_expenses'],
                'view_financial' => [],
                'view_reports' => [],
                'close_day' => [],
            ],
            'promotions' => [
                'view_sales' => [],
                'create_sales' => ['view_sales'],
                'edit_sales' => ['view_sales'],
                'delete_sales' => ['view_sales'],
            ],
            'installments' => [
                'view_installments' => [],
                'create_installments' => ['view_installments'],
                'dismiss_installment_notifications' => ['view_installments'],
                'delete_installments' => ['view_installments'],
            ],
            'inventory_tools' => [
                'view_tags' => [],
                'create_tags' => ['view_tags'],
                'edit_tags' => ['view_tags'],
                'delete_tags' => ['view_tags'],
            ],
            'restaurant' => [
                'view_kitchen' => [],
                'manage_tables' => [],
            ],
            'administration' => [
                'manage_employees' => [],
                'view_team_activity' => [],
                'manage_settings' => [],
            ],
        ];
    }

    /**
     * Keys that give broad or sensitive access. The picker highlights them.
     *
     * @return list<string>
     */
    public static function sensitive(): array
    {
        return [
            'delete_products', 'delete_bills', 'delete_customers', 'delete_suppliers',
            'delete_purchase_bills', 'delete_expenses', 'view_financial', 'manage_employees',
            'manage_settings', 'manage_payments_receipts', 'view_team_activity',
        ];
    }

    /**
     * Old form values that no longer exist as keys. They expand to the granular keys.
     *
     * @return array<string, list<string>>
     */
    public static function legacyAliases(): array
    {
        return [
            'manage_products' => ['view_products', 'create_products', 'edit_products', 'delete_products'],
            'manage_bills' => ['view_bills', 'create_bills', 'edit_bills', 'delete_bills'],
            'manage_customers' => ['view_customers', 'create_customers', 'edit_customers', 'delete_customers'],
            'manage_suppliers' => ['view_suppliers', 'create_suppliers', 'edit_suppliers', 'delete_suppliers'],
            'manage_purchase_bills' => ['view_purchase_bills', 'create_purchase_bills', 'edit_purchase_bills', 'delete_purchase_bills'],
            'manage_tags' => ['view_tags', 'create_tags', 'edit_tags', 'delete_tags'],
            'manage_expenses' => ['view_expenses', 'create_expenses', 'edit_expenses', 'delete_expenses'],
        ];
    }

    /**
     * Ready-made roles an owner can start from.
     *
     * @return array<string, list<string>>
     */
    public static function presets(): array
    {
        return [
            'cashier' => [
                'view_products', 'view_bills', 'create_bills', 'view_customers', 'create_customers',
            ],
            'senior_cashier' => [
                'view_products', 'view_bills', 'create_bills', 'edit_bills',
                'view_customers', 'create_customers', 'edit_customers', 'manage_payments_receipts',
                'view_installments', 'create_installments', 'close_day',
            ],
            'warehouse' => [
                'view_products', 'create_products', 'edit_products', 'view_tags', 'create_tags',
                'view_suppliers', 'view_purchase_bills', 'create_purchase_bills',
            ],
            'accountant' => [
                'view_bills', 'view_customers', 'manage_payments_receipts', 'view_suppliers',
                'view_purchase_bills', 'view_expenses', 'create_expenses', 'edit_expenses',
                'view_financial', 'view_reports', 'view_team_activity', 'close_day',
            ],
            'waiter' => [
                'view_products', 'view_bills', 'create_bills', 'view_customers', 'view_kitchen',
            ],
            'kitchen' => [
                'view_kitchen',
            ],
            'manager' => self::all(),
        ];
    }

    /**
     * Every valid permission key.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $keys = [];
        foreach (self::groups() as $permissions) {
            foreach (array_keys($permissions) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::all(), true);
    }

    /**
     * Laravel validation rule fragment for permissions.* inputs (legacy aliases accepted).
     */
    public static function validationRule(): string
    {
        return 'string|in:' . implode(',', array_merge(self::all(), array_keys(self::legacyAliases())));
    }

    /**
     * Expand legacy aliases only, without adding implied permissions.
     *
     * @param  iterable<string>|null  $keys
     * @return list<string>
     */
    public static function expandLegacyAliases(?iterable $keys): array
    {
        $expanded = [];

        foreach ($keys ?? [] as $key) {
            if (! is_string($key)) {
                continue;
            }

            foreach (self::legacyAliases()[$key] ?? [$key] as $resolved) {
                if (self::isValid($resolved)) {
                    $expanded[$resolved] = true;
                }
            }
        }

        return array_values(array_filter(self::all(), fn (string $key) => isset($expanded[$key])));
    }

    /**
     * Expand legacy aliases, add implied permissions, drop unknown keys and de-duplicate.
     *
     * @param  iterable<string>|null  $keys
     * @return list<string>
     */
    public static function normalize(?iterable $keys): array
    {
        $implied = [];
        foreach (self::groups() as $permissions) {
            foreach ($permissions as $key => $implies) {
                $implied[$key] = $implies;
            }
        }

        $result = [];
        foreach (self::expandLegacyAliases($keys) as $expanded) {
            if (isset($implied[$expanded])) {
                $result[$expanded] = true;
                foreach ($implied[$expanded] as $dependency) {
                    $result[$dependency] = true;
                }
            }
        }

        // Keep catalog order so stored arrays are stable.
        return array_values(array_filter(self::all(), fn (string $key) => isset($result[$key])));
    }

    /**
     * Group definitions ready for views.
     *
     * @return array<string, array{label: string, permissions: list<array{key: string, label: string, sensitive: bool, implies: list<string>}>}>
     */
    public static function forPicker(): array
    {
        $sensitive = self::sensitive();
        $picker = [];

        foreach (self::groups() as $group => $permissions) {
            $items = [];
            foreach ($permissions as $key => $implies) {
                $items[] = [
                    'key' => $key,
                    'label' => __('permissions.keys.' . $key),
                    'sensitive' => in_array($key, $sensitive, true),
                    'implies' => $implies,
                ];
            }

            $picker[$group] = [
                'label' => __('permissions.groups.' . $group),
                'permissions' => $items,
            ];
        }

        return $picker;
    }
}

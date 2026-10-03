<?php

namespace App\Support;

class ReportCatalog
{
    public static function rows(): array
    {
        $bills = [
            'id' => 'Bill #', 'created_at' => 'Date', 'customer_name' => 'Customer',
            'phone' => 'Phone', 'creator_name' => 'Created By', 'total_price' => 'Total',
            'profit' => 'Profit', 'is_damaged' => 'Damaged', 'is_returned' => 'Returned', 'note' => 'Note',
        ];
        $purchases = [
            'id' => 'Bill #', 'purchase_date' => 'Date', 'supplier.name' => 'Supplier',
            'creator.name' => 'Created By', 'reference_number' => 'Reference',
            'total_amount' => 'Total', 'notes' => 'Note',
        ];

        return [
            'sale_bills' => ['label' => 'All Sale Bills', 'columns' => $bills],
            'customer_bills' => ['label' => 'Customer Bills', 'columns' => $bills, 'filter' => 'customer_id'],
            'customer_statement' => ['label' => 'Customer Statement', 'columns' => $bills, 'filter' => 'customer_id'],
            'customer_payments' => ['label' => 'Customer Payments', 'filter' => 'customer_id', 'columns' => [
                'id' => '#', 'created_at' => 'Date', 'customer_name' => 'Customer', 'phone' => 'Phone',
                'amount' => 'Amount', 'type' => 'Payment Type', 'note' => 'Note',
            ]],
            'customer_balances' => ['label' => 'Customer Balances', 'columns' => [
                'id' => '#', 'name' => 'Customer', 'phone' => 'Phone', 'balance' => 'Balance',
            ]],
            'all_purchase_bills' => ['label' => 'All Purchase Bills', 'columns' => $purchases, 'filter' => 'supplier_id'],
            'supplier_purchase_bills' => ['label' => 'Supplier Purchase Bills', 'columns' => $purchases, 'filter' => 'supplier_id'],
            'supplier_payments' => ['label' => 'Supplier Payments', 'filter' => 'supplier_id', 'columns' => [
                'id' => '#', 'payment_date' => 'Date', 'supplier_name' => 'Supplier', 'phone' => 'Phone',
                'amount' => 'Amount', 'type' => 'Payment Type', 'note' => 'Note',
            ]],
            'supplier_balances' => ['label' => 'Supplier Balances', 'columns' => [
                'id' => '#', 'name' => 'Supplier', 'phone' => 'Phone', 'balance' => 'Balance',
            ]],
            'employee_payments' => ['label' => 'Employee Salary Payments', 'filter' => 'employee_id', 'columns' => [
                'id' => '#', 'payment_date' => 'Date', 'employee.name' => 'Employee',
                'employee.job_title' => 'Job Title', 'amount' => 'Amount', 'type' => 'Payment Type', 'note' => 'Note',
            ]],
            'employee_work' => ['label' => 'Employee Work Report', 'columns' => $bills, 'filter' => 'employee_user_id'],
            'expenses' => ['label' => 'Expenses Report', 'columns' => [
                'id' => '#', 'expense_date' => 'Date', 'title' => 'Title', 'amount' => 'Amount', 'note' => 'Note',
            ]],
        ] + self::productReports();
    }

    /** Money columns that are added up in report footers. */
    public const MONEY_KEYS = [
        'total_price', 'total_amount', 'profit', 'amount', 'balance', 'gross_sales', 'discounts', 'discount',
        'returned_value', 'revenue', 'cogs', 'line_total', 'stock_cost', 'stock_value', 'potential_profit',
        'purchase_cost', 'damaged_loss',
    ];

    /** Unit prices: formatted as money but never summed. */
    public const PRICE_KEYS = ['avg_price', 'cost_price', 'selling_price', 'avg_cost'];

    /** Quantities that are added up in report footers. */
    public const QTY_KEYS = ['qty_sold', 'qty_returned', 'net_qty', 'quantity', 'qty_purchased', 'qty_damaged', 'stock', 'products_count', 'suggested_order'];

    public const PERCENT_KEYS = ['margin', 'share', 'return_rate'];

    public static function productReports(): array
    {
        $c = fn (string $key) => 'charts.products.columns.'.$key;
        $columns = fn (array $keys) => collect($keys)->mapWithKeys(fn ($key) => [$key => $c($key)])->all();
        $product = ['name', 'barcode', 'category'];

        $definitions = [
            'product_sales' => [...$product, 'qty_sold', 'qty_returned', 'net_qty', 'gross_sales', 'discounts', 'returned_value', 'revenue', 'avg_price', 'cogs', 'profit', 'margin', 'bills_count', 'stock', 'last_sold_at'],
            'top_selling_products' => [...$product, 'net_qty', 'revenue', 'profit', 'margin', 'bills_count', 'stock'],
            'most_profitable_products' => [...$product, 'profit', 'margin', 'revenue', 'cogs', 'net_qty'],
            'least_selling_products' => [...$product, 'net_qty', 'revenue', 'profit', 'stock', 'last_sold_at'],
            'unsold_products' => [...$product, 'stock', 'cost_price', 'selling_price', 'stock_cost', 'last_sold_at', 'days_without_sale'],
            'product_movement' => ['created_at', 'bill_id', 'movement', 'name', 'barcode', 'customer_name', 'creator_name', 'quantity', 'selling_price', 'discount', 'line_total', 'profit'],
            'category_sales' => ['category', 'products_count', 'qty_sold', 'qty_returned', 'net_qty', 'revenue', 'cogs', 'profit', 'margin', 'share'],
            'low_stock_products' => [...$product, 'stock', 'low_stock_threshold', 'stock_status', 'avg_daily_sales', 'days_of_stock', 'suggested_order'],
            'product_stock_valuation' => [...$product, 'stock', 'cost_price', 'selling_price', 'stock_cost', 'stock_value', 'potential_profit', 'margin'],
            'product_purchases' => [...$product, 'qty_purchased', 'purchase_cost', 'avg_cost', 'purchase_bills_count', 'suppliers', 'last_purchase_date', 'stock'],
            'damaged_products' => [...$product, 'qty_damaged', 'damaged_loss', 'bills_count', 'last_damaged_at'],
            'returned_products' => [...$product, 'qty_sold', 'qty_returned', 'return_rate', 'returned_value'],
        ];

        return collect($definitions)->mapWithKeys(fn ($keys, $type) => [$type => [
            'label' => 'charts.products.types.'.$type,
            'description' => 'charts.products.descriptions.'.$type,
            'group' => 'products',
            'columns' => $columns($keys),
            'dated' => ! in_array($type, ['low_stock_products', 'product_stock_valuation'], true),
        ]])->all();
    }

    public static function isProductReport(string $type): bool
    {
        return (self::rows()[$type]['group'] ?? null) === 'products';
    }

    /** Translate a catalog label: dotted labels are full keys, legacy ones live in messages.php. */
    public static function label(string $label): string
    {
        return str_contains($label, '.') ? __($label) : __('messages.'.$label);
    }

    public static function isSummable(string $key): bool
    {
        return in_array($key, self::MONEY_KEYS, true) || in_array($key, self::QTY_KEYS, true);
    }

    public static function footerValue(iterable $rows, string $key): string
    {
        $sum = collect($rows)->sum(fn ($row) => (float) data_get($row, $key));

        return in_array($key, self::QTY_KEYS, true) ? self::quantity($sum) : number_format($sum, 2);
    }

    private static function quantity(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }

    public static function value(mixed $row, string $key): string
    {
        $value = data_get($row, $key);
        if ($value instanceof \DateTimeInterface) {
            if (in_array($key, ['purchase_date', 'payment_date'], true)) {
                return $value->format('Y-m-d');
            }

            return ShopTime::local($value->format(DATE_ATOM), auth()->user()->ownerId())->format('Y-m-d H:i');
        }
        if (in_array($key, ['is_damaged', 'is_returned'], true)) {
            return $value ? __('messages.Yes') : __('messages.No');
        }
        if (in_array($key, self::MONEY_KEYS, true) || in_array($key, self::PRICE_KEYS, true)) {
            return number_format((float) $value, 2, '.', '');
        }
        if (in_array($key, self::PERCENT_KEYS, true)) {
            return $value === null || $value === '' ? '' : number_format((float) $value, 1, '.', '').'%';
        }
        if (in_array($key, self::QTY_KEYS, true)) {
            return self::quantity((float) $value);
        }
        if (is_float($value)) {
            return self::quantity($value);
        }

        return (string) ($value ?? '');
    }

    public static function profitLossRows(array $report): array
    {
        $rows = [
            [__('finance.common.item'), __('finance.reports.current_period'), __('finance.reports.previous_period')],
            [__('finance.common.from'), $report['from_date'], $report['compare']['from_date']],
            [__('finance.common.to'), $report['to_date'], $report['compare']['to_date']],
        ];
        foreach ([
            'revenue' => 'finance.reports.revenue', 'returns' => 'finance.reports.returns',
            'net_revenue' => 'finance.reports.net_revenue', 'cogs' => 'finance.reports.cogs',
            'discounts' => 'finance.day_close.discounts', 'gross_profit' => 'finance.reports.gross_profit',
            'expenses_total' => 'finance.reports.expenses', 'staff_payments' => 'finance.reports.staff_payments',
            'staff_refunds' => 'finance.cash_drawer.categories.staff_refund', 'damaged_loss' => 'finance.reports.damaged_loss',
            'net_profit' => 'finance.reports.net_profit', 'gross_margin' => 'finance.reports.gross_margin',
            'net_margin' => 'finance.reports.net_margin',
        ] as $key => $label) {
            $rows[] = [__($label), $report[$key], $report['compare'][$key]];
        }

        return $rows;
    }
}

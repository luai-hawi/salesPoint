<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ShopPurger
{
    public function preview(User $shop): array
    {
        $refs = $this->references($shop);
        $known = $this->knownTableCounts($shop, $refs);
        $dynamic = $this->dynamicTableCounts($shop);

        $tables = array_filter($known + $dynamic);
        ksort($tables);

        return [
            'tables' => $tables,
            'total' => array_sum($tables),
        ];
    }

    public function purge(User $shop): void
    {
        DB::transaction(function () use ($shop) {
            $refs = $this->references($shop);
            $paths = $this->productImagePaths($shop->id);
            $emails = $this->userEmails($refs['user_ids']);

            if ($refs['sale_ids']) {
                DB::table('sale_rules')->whereIn('sale_id', $refs['sale_ids'])->delete();
            }

            if ($refs['installment_payment_ids']) {
                DB::table('installment_dismissals')->whereIn('installment_payment_id', $refs['installment_payment_ids'])->delete();
            }

            if ($refs['installment_plan_ids']) {
                DB::table('installment_payments')->whereIn('installment_plan_id', $refs['installment_plan_ids'])->delete();
            }

            if ($refs['bill_ids']) {
                DB::table('bill_product')->whereIn('bill_id', $refs['bill_ids'])->delete();
            }

            if ($refs['purchase_bill_ids']) {
                DB::table('purchase_bill_product')->whereIn('purchase_bill_id', $refs['purchase_bill_ids'])->delete();
            }

            if ($refs['product_ids']) {
                foreach (['product_barcodes', 'product_imeis', 'batches'] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->whereIn('product_id', $refs['product_ids'])->delete();
                    }
                }
            }

            if ($refs['customer_ids'] && Schema::hasTable('customer_payments')) {
                DB::table('customer_payments')->whereIn('customer_id', $refs['customer_ids'])->delete();
            }

            if ($refs['supplier_ids'] && Schema::hasTable('supplier_payments')) {
                DB::table('supplier_payments')->whereIn('supplier_id', $refs['supplier_ids'])->delete();
            }

            if (Schema::hasTable('subscription_payments')) {
                DB::table('subscription_payments')->where('user_id', $shop->id)->delete();
            }

            if (Schema::hasTable('activity_logs')) {
                DB::table('activity_logs')
                    ->where('owner_id', $shop->id)
                    ->delete();
            }

            foreach ([
                'installment_plans',
                'sales',
                'capital_entries',
                'expenses',
                'tags',
                'attendance_locations',
                'attendance_records',
                'employee_devices',
                'employee_credentials',
                'employee_adjustments',
                'employee_leaves',
                'product_variant_groups',
                'customers',
                'suppliers',
                'products',
                'bills',
                'purchase_bills',
            ] as $table) {
                $this->deleteByOwner($table, $shop->id);
            }

            if ($refs['staff_employee_ids'] && Schema::hasTable('employee_payments')) {
                DB::table('employee_payments')->whereIn('employee_id', $refs['staff_employee_ids'])->delete();
            }
            if (Schema::hasTable('employees')) {
                DB::table('employees')->where('shop_owner_id', $shop->id)->delete();
            }

            $this->deleteDynamicOwnerRows($shop->id);

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->whereIn('user_id', $refs['user_ids'])->delete();
            }
            if ($emails && Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
            }

            DB::table('users')->where('shop_owner_id', $shop->id)->delete();
            DB::table('users')->where('id', $shop->id)->delete();

            $this->deleteOrphanedImages($paths, $shop->id);
        });
    }

    private function references(User $shop): array
    {
        $productIds = $this->pluckIds('products', 'user_id', $shop->id);
        $customerIds = $this->pluckIds('customers', 'user_id', $shop->id);
        $supplierIds = $this->pluckIds('suppliers', 'user_id', $shop->id);
        $billIds = $this->pluckIds('bills', 'user_id', $shop->id);
        $purchaseBillIds = $this->pluckIds('purchase_bills', 'user_id', $shop->id);
        $installmentPlanIds = $this->pluckIds('installment_plans', 'user_id', $shop->id);
        $installmentPaymentIds = Schema::hasTable('installment_payments')
            ? DB::table('installment_payments')->where('user_id', $shop->id)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
        $saleIds = $this->pluckIds('sales', 'user_id', $shop->id);
        $staffEmployeeIds = $this->pluckIds('employees', 'shop_owner_id', $shop->id);
        $userIds = DB::table('users')->where('id', $shop->id)->orWhere('shop_owner_id', $shop->id)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return compact(
            'productIds',
            'customerIds',
            'supplierIds',
            'billIds',
            'purchaseBillIds',
            'installmentPlanIds',
            'installmentPaymentIds',
            'saleIds',
            'staffEmployeeIds',
            'userIds',
        ) + [
            'product_ids' => $productIds,
            'customer_ids' => $customerIds,
            'supplier_ids' => $supplierIds,
            'bill_ids' => $billIds,
            'purchase_bill_ids' => $purchaseBillIds,
            'installment_plan_ids' => $installmentPlanIds,
            'installment_payment_ids' => $installmentPaymentIds,
            'sale_ids' => $saleIds,
            'staff_employee_ids' => $staffEmployeeIds,
            'user_ids' => $userIds,
        ];
    }

    private function knownTableCounts(User $shop, array $refs): array
    {
        return [
            'users' => count($refs['user_ids']),
            'sessions' => $this->countTable('sessions', 'user_id', $refs['user_ids']),
            'employees' => count($refs['staff_employee_ids']),
            'employee_payments' => $this->countTable('employee_payments', 'employee_id', $refs['staff_employee_ids']),
            'products' => count($refs['product_ids']),
            'product_barcodes' => $this->countTable('product_barcodes', 'product_id', $refs['product_ids']),
            'product_imeis' => $this->countTable('product_imeis', 'product_id', $refs['product_ids']),
            'batches' => $this->countTable('batches', 'product_id', $refs['product_ids']),
            'product_variant_groups' => $this->countTable('product_variant_groups', 'user_id', [$shop->id]),
            'customers' => count($refs['customer_ids']),
            'customer_payments' => $this->countTable('customer_payments', 'customer_id', $refs['customer_ids']),
            'bills' => count($refs['bill_ids']),
            'bill_product' => $this->countTable('bill_product', 'bill_id', $refs['bill_ids']),
            'suppliers' => count($refs['supplier_ids']),
            'supplier_payments' => $this->countTable('supplier_payments', 'supplier_id', $refs['supplier_ids']),
            'purchase_bills' => count($refs['purchase_bill_ids']),
            'purchase_bill_product' => $this->countTable('purchase_bill_product', 'purchase_bill_id', $refs['purchase_bill_ids']),
            'sales' => count($refs['sale_ids']),
            'sale_rules' => $this->countTable('sale_rules', 'sale_id', $refs['sale_ids']),
            'installment_plans' => count($refs['installment_plan_ids']),
            'installment_payments' => count($refs['installment_payment_ids']),
            'installment_dismissals' => $this->countTable('installment_dismissals', 'installment_payment_id', $refs['installment_payment_ids']),
            'capital_entries' => $this->countTable('capital_entries', 'user_id', [$shop->id]),
            'expenses' => $this->countTable('expenses', 'user_id', [$shop->id]),
            'tags' => $this->countTable('tags', 'user_id', [$shop->id]),
            'subscription_payments' => $this->countTable('subscription_payments', 'user_id', [$shop->id]),
            'activity_logs' => Schema::hasTable('activity_logs')
                ? DB::table('activity_logs')->where('owner_id', $shop->id)->count()
                : 0,
        ];
    }

    private function dynamicTableCounts(User $shop): array
    {
        $counts = [];
        foreach ($this->tableListing() as $table) {
            if ($this->skipTable($table) || isset($counts[$table])) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $query = DB::table($table);
            $matched = false;
            if (in_array('user_id', $columns, true)) {
                $query->where('user_id', $shop->id);
                $matched = true;
            }
            if (in_array('shop_owner_id', $columns, true)) {
                $matched
                    ? $query->orWhere('shop_owner_id', $shop->id)
                    : $query->where('shop_owner_id', $shop->id);
                $matched = true;
            }

            if ($matched) {
                $count = $query->count();
                if ($count > 0) {
                    $counts[$table] = $count;
                }
            }
        }

        return $counts;
    }

    private function deleteDynamicOwnerRows(int $ownerId): void
    {
        foreach ($this->tableListing() as $table) {
            if ($this->skipTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $query = DB::table($table);
            $matched = false;
            if (in_array('user_id', $columns, true)) {
                $query->where('user_id', $ownerId);
                $matched = true;
            }
            if (in_array('shop_owner_id', $columns, true)) {
                $matched
                    ? $query->orWhere('shop_owner_id', $ownerId)
                    : $query->where('shop_owner_id', $ownerId);
                $matched = true;
            }

            if ($matched) {
                $query->delete();
            }
        }
    }

    private function deleteByOwner(string $table, int $ownerId): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = Schema::getColumnListing($table);
        if (in_array('user_id', $columns, true)) {
            DB::table($table)->where('user_id', $ownerId)->delete();
        } elseif (in_array('shop_owner_id', $columns, true)) {
            DB::table($table)->where('shop_owner_id', $ownerId)->delete();
        }
    }

    /**
     * @return list<int>
     */
    private function pluckIds(string $table, string $column, int $ownerId): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)->where($column, $ownerId)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $userIds
     * @return list<string>
     */
    private function userEmails(array $userIds): array
    {
        if (! $userIds) {
            return [];
        }

        return DB::table('users')
            ->whereIn('id', $userIds)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && $email !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     */
    private function countTable(string $table, string $column, array $ids): int
    {
        if (! $ids || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->whereIn($column, $ids)->count();
    }

    /**
     * @return list<string>
     */
    private function productImagePaths(int $ownerId): array
    {
        $paths = [];

        Product::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereNotNull('pictures')
            ->select('pictures')
            ->chunk(200, function ($products) use (&$paths) {
                foreach ($products as $product) {
                    foreach (ShopStorageService::decodePictures($product->pictures) as $path) {
                        $paths[$path] = true;
                    }
                }
            });

        return array_keys($paths);
    }

    /**
     * @param  list<string>  $paths
     */
    private function deleteOrphanedImages(array $paths, int $ownerId): void
    {
        if (! $paths) {
            return;
        }

        $disk = Storage::disk('public');
        $remaining = [];

        Product::withoutGlobalScopes()
            ->where('user_id', '!=', $ownerId)
            ->whereNotNull('pictures')
            ->select('pictures')
            ->chunk(200, function ($products) use (&$remaining) {
                foreach ($products as $product) {
                    foreach (ShopStorageService::decodePictures($product->pictures) as $path) {
                        $remaining[$path] = true;
                    }
                }
            });

        foreach ($paths as $path) {
            if (! isset($remaining[$path]) && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function tableListing(): array
    {
        return array_values(DB::getSchemaBuilder()->getTableListing());
    }

    private function skipTable(string $table): bool
    {
        return in_array($table, [
            'users',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'migrations',
            'password_reset_tokens',
            'activity_logs',
            'platform_settings',
            'subscription_payments',
        ], true);
    }
}

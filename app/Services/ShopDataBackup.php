<?php

namespace App\Services;

use App\Support\ExportSanitizer;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full copy of one shop's own data: every table that belongs to the shop, all rows and columns,
 * as Excel-ready UTF-8 CSV files inside one ZIP. Secrets (passwords, tokens, PINs, biometric keys)
 * are never written. Rows are streamed in chunks so large shops fit in shared-hosting memory.
 */
class ShopDataBackup
{
    private const SECRET_COLUMN = '/(password|remember_token|token|secret|pin_hash|^pin$|public_key|credential_id|api_key|two_factor|session_id|_key$|admin_notes)/i';

    /** Only these columns of program accounts are exported (admin notes and licence internals stay private). */
    private const ACCOUNT_COLUMNS = ['id', 'name', 'owner_name', 'email', 'phone_number', 'role', 'shop_owner_id', 'permissions', 'is_active', 'timezone', 'created_at', 'updated_at'];

    /** @return array<string, \Closure(int): Builder> */
    private function tables(): array
    {
        $own = fn (string $table, string $column = 'user_id') => fn (int $owner) => DB::table($table)->where("{$table}.{$column}", $owner);
        $child = fn (string $table, string $foreignKey, string $parent, string $parentOwner = 'user_id') => fn (int $owner) => DB::table($table)
            ->whereIn("{$table}.{$foreignKey}", DB::table($parent)->select('id')->where($parentOwner, $owner));

        return [
            'products' => $own('products'),
            'product_barcodes' => $child('product_barcodes', 'product_id', 'products'),
            'product_variant_groups' => $own('product_variant_groups'),
            'product_imeis' => $own('product_imeis'),
            'batches' => $own('batches'),
            'tags' => $own('tags'),
            'sales' => $own('sales'),
            'sale_rules' => $child('sale_rules', 'sale_id', 'sales'),
            'customers' => $own('customers'),
            'bills' => $own('bills'),
            'bill_product' => $child('bill_product', 'bill_id', 'bills'),
            'customer_payments' => $own('customer_payments'),
            'installment_plans' => $own('installment_plans'),
            'installment_payments' => $own('installment_payments'),
            'held_bills' => $own('held_bills'),
            'suppliers' => $own('suppliers'),
            'purchase_bills' => $own('purchase_bills'),
            'purchase_bill_product' => $child('purchase_bill_product', 'purchase_bill_id', 'purchase_bills'),
            'supplier_payments' => $own('supplier_payments'),
            'expenses' => $own('expenses'),
            'capital_entries' => $own('capital_entries'),
            'cash_movements' => $own('cash_movements'),
            'day_closings' => $own('day_closings'),
            'employees' => $own('employees', 'shop_owner_id'),
            'employee_payments' => $child('employee_payments', 'employee_id', 'employees', 'shop_owner_id'),
            'employee_adjustments' => $own('employee_adjustments'),
            'employee_leaves' => $own('employee_leaves'),
            'attendance_locations' => $own('attendance_locations'),
            'attendance_records' => $own('attendance_records'),
            'restaurant_tables' => $own('restaurant_tables'),
            'restaurant_orders' => $own('restaurant_orders'),
            'kitchen_tickets' => $own('kitchen_tickets'),
            'program_accounts' => fn (int $owner) => DB::table('users')->where(fn ($q) => $q->where('users.id', $owner)->orWhere('users.shop_owner_id', $owner)),
            'activity_logs' => $own('activity_logs', 'owner_id'),
        ];
    }

    /**
     * Writes the backup ZIP and returns its path plus the row count of every table.
     *
     * @return array{path: string, counts: array<string, int>}
     */
    public function createZip(int $ownerId, string $directory): array
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . 'backup-' . $ownerId . '-' . now()->format('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the backup file.');
        }

        $counts = [];
        $tempFiles = [];
        foreach ($this->tables() as $name => $query) {
            $temp = tempnam(sys_get_temp_dir(), 'shopbk');
            $counts[$name] = $this->writeCsv($name, $query($ownerId), $temp);
            if ($counts[$name] === null) {
                @unlink($temp);
                unset($counts[$name]);

                continue;
            }
            $zip->addFile($temp, $name . '.csv');
            $tempFiles[] = $temp;
        }

        $zip->addFromString('backup-info.json', json_encode([
            'shop_id' => $ownerId,
            'created_at_utc' => now()->utc()->toDateTimeString(),
            'tables' => $counts,
            'note' => 'Excel-ready UTF-8 CSV files. Passwords, tokens and biometric keys are not included.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        foreach ($tempFiles as $temp) {
            @unlink($temp);
        }

        return ['path' => $path, 'counts' => $counts];
    }

    /** Returns the number of rows written, or null when the table does not exist on this server. */
    private function writeCsv(string $name, Builder $query, string $file): ?int
    {
        $table = $query->from;
        if (! Schema::hasTable($table)) {
            return null;
        }

        $columns = array_values(array_filter(
            Schema::getColumnListing($table),
            fn ($column) => ! preg_match(self::SECRET_COLUMN, $column)
                && ($table !== 'users' || in_array($column, self::ACCOUNT_COLUMNS, true))
        ));
        if ($columns === []) {
            return null;
        }

        $handle = fopen($file, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $columns, ',', '"', '');

        $count = 0;
        $query->select(array_map(fn ($column) => "{$table}.{$column}", $columns));
        $writeRow = function ($row) use ($handle, $columns, &$count) {
            $row = (array) $row;
            fputcsv($handle, array_map(fn ($column) => ExportSanitizer::csvValue($row[$column] ?? null), $columns), ',', '"', '');
            $count++;
        };

        if (in_array('id', $columns, true)) {
            $query->chunkById(1000, function ($rows) use ($writeRow) {
                foreach ($rows as $row) {
                    $writeRow($row);
                }
            }, "{$table}.id", 'id');
        } else {
            foreach ($query->cursor() as $row) {
                $writeRow($row);
            }
        }

        fclose($handle);

        return $count;
    }
}

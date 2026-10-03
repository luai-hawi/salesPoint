<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\CapitalEntry;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Records "who did what" for the business-critical models.
 * Only actions performed by an authenticated person are logged (never console/system work).
 */
class ActivityObserver
{
    /** Bill id => activity log id, so the total computed after creation lands on the "created" entry. */
    private static array $pendingBillLogs = [];

    public function created(Model $model): void
    {
        $definition = $this->definition($model);
        if (! $definition || ! auth()->check()) {
            return;
        }

        $properties = [];
        if ($model instanceof Bill) {
            $properties = array_filter([
                'customer_id' => $model->customer_id,
                'is_returned' => $model->is_returned ? 1 : null,
                'is_damaged' => $model->is_damaged ? 1 : null,
            ]);
        }

        $log = ActivityLogger::record(
            'created',
            $definition['type'],
            $model,
            $properties,
            $this->amount($model, $definition),
            $this->label($model, $definition),
            $this->owner($model, $definition),
        );

        if ($model instanceof Bill && $log) {
            self::$pendingBillLogs[$model->getKey()] = $log->getKey();
        }
    }

    public function updated(Model $model): void
    {
        $definition = $this->definition($model);
        if (! $definition || ! auth()->check()) {
            return;
        }

        if ($model instanceof Bill && $model->wasRecentlyCreated && isset(self::$pendingBillLogs[$model->getKey()])) {
            if ($model->wasChanged('total_price')) {
                ActivityLog::whereKey(self::$pendingBillLogs[$model->getKey()])
                    ->update(['amount' => $model->total_price]);
            }

            return;
        }

        $changes = [];
        foreach ($definition['significant'] as $attribute) {
            if (! $model->wasChanged($attribute)) {
                continue;
            }

            if ($attribute === 'password') {
                $changes[$attribute] = ['***', '***'];
                continue;
            }

            $changes[$attribute] = [$model->getOriginal($attribute), $model->getAttribute($attribute)];
        }

        if (! $changes) {
            return;
        }

        ActivityLogger::record(
            'updated',
            $definition['type'],
            $model,
            ['changes' => $changes],
            $this->amount($model, $definition),
            $this->label($model, $definition),
            $this->owner($model, $definition),
        );
    }

    public function deleted(Model $model): void
    {
        $definition = $this->definition($model);
        if (! $definition || ! auth()->check()) {
            return;
        }

        ActivityLogger::record(
            'deleted',
            $definition['type'],
            $model,
            [],
            $this->amount($model, $definition),
            $this->label($model, $definition),
            $this->owner($model, $definition),
        );
    }

    /**
     * @return array{type: string, label: string, amount: ?string, owner: string, significant: list<string>}|null
     */
    private function definition(Model $model): ?array
    {
        return match (true) {
            $model instanceof Bill => ['type' => 'bill', 'label' => 'id', 'amount' => 'total_price', 'owner' => 'user_id', 'significant' => ['total_price', 'customer_id']],
            $model instanceof CustomerPayment => ['type' => 'customer_payment', 'label' => 'customer', 'amount' => 'amount', 'owner' => 'user_id', 'significant' => ['amount', 'type']],
            $model instanceof SupplierPayment => ['type' => 'supplier_payment', 'label' => 'supplier', 'amount' => 'amount', 'owner' => 'user_id', 'significant' => ['amount', 'type']],
            $model instanceof PurchaseBill => ['type' => 'purchase_bill', 'label' => 'id', 'amount' => 'total_amount', 'owner' => 'user_id', 'significant' => ['total_amount', 'supplier_id']],
            $model instanceof Expense => ['type' => 'expense', 'label' => 'title', 'amount' => 'amount', 'owner' => 'user_id', 'significant' => ['amount', 'title']],
            $model instanceof CapitalEntry => ['type' => 'capital_entry', 'label' => 'note', 'amount' => 'amount', 'owner' => 'user_id', 'significant' => ['amount']],
            $model instanceof Customer => ['type' => 'customer', 'label' => 'name', 'amount' => null, 'owner' => 'user_id', 'significant' => ['name', 'phone']],
            $model instanceof Supplier => ['type' => 'supplier', 'label' => 'name', 'amount' => null, 'owner' => 'user_id', 'significant' => ['name', 'phone']],
            $model instanceof Product => ['type' => 'product', 'label' => 'name', 'amount' => null, 'owner' => 'user_id', 'significant' => ['name', 'selling_price', 'is_active']],
            $model instanceof EmployeePayment => ['type' => 'employee_payment', 'label' => 'employee', 'amount' => 'amount', 'owner' => 'employee', 'significant' => ['amount', 'type']],
            $model instanceof Employee => ['type' => 'staff_member', 'label' => 'name', 'amount' => null, 'owner' => 'shop_owner_id', 'significant' => ['name', 'monthly_salary', 'job_title']],
            $model instanceof User && $model->role === 'employee' => ['type' => 'team_account', 'label' => 'name', 'amount' => null, 'owner' => 'shop_owner_id', 'significant' => ['name', 'email', 'permissions', 'password']],
            default => null,
        };
    }

    private function label(Model $model, array $definition): ?string
    {
        $value = match ($definition['label']) {
            'id' => '#' . $model->getKey(),
            'customer' => optional($model->customer)->name,
            'supplier' => optional($model->supplier)->name,
            'employee' => optional($model->employee)->name,
            default => $model->getAttribute($definition['label']),
        };

        return $value === null ? null : mb_substr((string) $value, 0, 250);
    }

    private function amount(Model $model, array $definition): ?float
    {
        return $definition['amount'] ? (float) $model->getAttribute($definition['amount']) : null;
    }

    private function owner(Model $model, array $definition): ?int
    {
        if ($definition['owner'] === 'employee') {
            $owner = optional($model->employee)->shop_owner_id;
        } else {
            $owner = $model->getAttribute($definition['owner']);
        }

        return $owner ? (int) $owner : null;
    }
}

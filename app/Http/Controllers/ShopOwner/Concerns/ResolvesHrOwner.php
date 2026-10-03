<?php

namespace App\Http\Controllers\ShopOwner\Concerns;

use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeAdjustment;
use App\Models\EmployeeCredential;
use App\Models\EmployeeDevice;
use App\Models\EmployeeLeave;
use App\Models\EmployeePayment;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ResolvesHrOwner
{
    protected function hrActor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    protected function hrOwnerId(): int
    {
        return (int) ($this->hrActor()->ownerId() ?? $this->hrActor()->id);
    }

    protected function hrOwner(): User
    {
        $actor = $this->hrActor();

        if ($actor->isOwnerAccount()) {
            return $actor;
        }

        return User::withoutGlobalScopes()->findOrFail($this->hrOwnerId());
    }

    protected function authorizeEmployee(Employee $employee): void
    {
        if ($this->hrActor()->role === 'admin') {
            return;
        }

        abort_unless((int) $employee->shop_owner_id === $this->hrOwnerId(), 403);
    }

    protected function ownerIdForEmployee(Employee $employee): int
    {
        return (int) $employee->shop_owner_id;
    }

    protected function ownerForEmployee(Employee $employee): User
    {
        if ($employee->relationLoaded('shopOwner') && $employee->shopOwner) {
            return $employee->shopOwner;
        }

        return User::withoutGlobalScopes()->findOrFail($this->ownerIdForEmployee($employee));
    }

    protected function ownerIdForRecord(AttendanceRecord $record): int
    {
        return (int) $record->user_id;
    }

    protected function ownerForRecord(AttendanceRecord $record): User
    {
        $record->loadMissing('employee.shopOwner');

        if ($record->employee?->shopOwner) {
            return $record->employee->shopOwner;
        }

        return User::withoutGlobalScopes()->findOrFail($this->ownerIdForRecord($record));
    }

    protected function ownerIdForAdjustment(EmployeeAdjustment $adjustment): int
    {
        return (int) ($adjustment->employee?->shop_owner_id ?? $adjustment->user_id);
    }

    protected function ownerIdForLeave(EmployeeLeave $leave): int
    {
        return (int) ($leave->employee?->shop_owner_id ?? $leave->user_id);
    }

    protected function authorizeLocation(AttendanceLocation $location): void
    {
        if ($this->hrActor()->role === 'admin') {
            return;
        }

        abort_unless((int) $location->user_id === $this->hrOwnerId(), 403);
    }

    protected function authorizeRecord(AttendanceRecord $record): void
    {
        if ($this->hrActor()->role === 'admin') {
            return;
        }

        abort_unless((int) $record->user_id === $this->hrOwnerId(), 403);
    }

    protected function authorizePayment(EmployeePayment $payment): void
    {
        $payment->loadMissing('employee');
        $this->authorizeEmployee($payment->employee);
    }

    protected function authorizeAdjustment(EmployeeAdjustment $adjustment): void
    {
        $adjustment->loadMissing('employee');
        $this->authorizeEmployee($adjustment->employee);
    }

    protected function authorizeLeave(EmployeeLeave $leave): void
    {
        $leave->loadMissing('employee');
        $this->authorizeEmployee($leave->employee);
    }

    protected function authorizeDevice(EmployeeDevice $device): void
    {
        $device->loadMissing('employee');
        $this->authorizeEmployee($device->employee);
    }

    protected function authorizeCredential(EmployeeCredential $credential): void
    {
        $credential->loadMissing('employee');
        $this->authorizeEmployee($credential->employee);
    }

    protected function claimPaymentIdempotency(Request $request, string $token): void
    {
        $signature = sha1('POST|' . $this->paymentIdempotencyPath($request) . '|' . mb_substr($token, 0, 80));

        $inserted = IdempotencyKey::query()->insertOrIgnore([
            'user_id' => $this->hrActor()->id,
            'key' => $signature,
            'request_key' => mb_substr($token, 0, 80),
            'method' => 'POST',
            'path' => $this->paymentIdempotencyPath($request),
            'status' => 202,
            'body' => null,
            'body_truncated' => false,
            'location' => null,
            'content_type' => null,
            'created_at' => now(),
        ]);

        if ($inserted === 0) {
            throw ValidationException::withMessages([
                'amount' => __('hr_owner.payment_already_processed'),
            ]);
        }
    }

    protected function paymentIdempotencyPath(Request $request): string
    {
        return mb_substr('/' . ltrim($request->path(), '/'), 0, 255);
    }
}

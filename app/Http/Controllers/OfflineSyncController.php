<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\User;
use App\Services\BillService;
use App\Services\CustomerLedger;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfflineSyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $user = auth()->user();
        $ownerId = $user->ownerId();
        if (! $ownerId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payload = $request->all();
        foreach (['bills', 'payments', 'installments'] as $bucket) {
            if (! isset($payload[$bucket]) || ! is_array($payload[$bucket])) {
                continue;
            }

            $payload[$bucket] = array_map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                if (! isset($item['local_id']) && isset($item['localId'])) {
                    $item['local_id'] = $item['localId'];
                }

                return $item;
            }, $payload[$bucket]);
        }
        $request->merge($payload);

        $validated = $request->validate([
            'bills' => 'nullable|array|max:100',
            'bills.*.local_id' => 'required|string|max:100',
            'bills.*.product_ids' => 'required|array|min:1',
            'bills.*.product_ids.*' => 'required|integer|min:1',
            'bills.*.quantities' => 'required|array',
            'bills.*.quantities.*' => 'required|numeric',
            'bills.*.discounts' => 'required|array',
            'bills.*.discounts.*' => 'nullable|numeric',
            'bills.*.cost_prices' => 'required|array',
            'bills.*.cost_prices.*' => 'nullable|numeric',
            'bills.*.selling_prices' => 'required|array',
            'bills.*.selling_prices.*' => 'nullable|numeric',
            'bills.*.discount_types' => 'nullable|array',
            'bills.*.discount_types.*' => 'nullable|string|in:total,per-unit',
            'bills.*.product_tags' => 'nullable|array',
            'bills.*.product_tags.*' => 'nullable|string|max:500',
            'bills.*.return_costs' => 'nullable|array',
            'bills.*.return_costs.*' => 'nullable|numeric',
            'bills.*.customer_id' => 'nullable|integer',
            'bills.*.note' => 'nullable|string|max:1000',
            'bills.*.bill_date' => 'nullable|date',
            'bills.*.is_damaged' => 'nullable|boolean',
            'bills.*.is_returned' => 'nullable|boolean',
            'bills.*.paid_amount' => 'nullable|numeric|min:0',
            'bills.*.payment_method' => 'nullable|string|in:cash,card,transfer,check',
            'bills.*.client_uuid' => 'nullable|string|max:64',

            'payments' => 'nullable|array|max:200',
            'payments.*.local_id' => 'required|string|max:100',
            'payments.*.customer_id' => 'required|integer|min:1',
            'payments.*.amount' => 'required|numeric|not_in:0',
            'payments.*.type' => 'required|string|in:cash,card,transfer,check',
            'payments.*.note' => 'nullable|string|max:255',
            'payments.*.payment_date' => 'nullable|date',
            'payments.*.bill_id' => 'nullable',
            'payments.*.client_uuid' => 'nullable|string|max:64',

            'installments' => 'nullable|array|max:100',
            'installments.*.local_id' => 'required|string|max:100',
            'installments.*.bill_id' => 'nullable|string|max:100',
            'installments.*.customer_id' => 'required|integer|min:1',
            'installments.*.total_amount' => 'required|numeric|min:0.01',
            'installments.*.initial_payment' => 'nullable|numeric|min:0',
            'installments.*.note' => 'nullable|string|max:1000',
            'installments.*.payments' => 'nullable|array',
            'installments.*.payments.*.due_date' => 'nullable|date',
            'installments.*.payments.*.amount' => 'nullable|numeric|min:0.01',
            'installments.*.payments.*.note' => 'nullable|string|max:500',
            'installments.*.client_uuid' => 'nullable|string|max:64',
        ]);

        $billResults = $this->syncBills($validated['bills'] ?? [], $user);
        $localBillIdMap = [];
        foreach ($billResults as $result) {
            if (($result['success'] ?? false) && ! empty($result['bill_id'])) {
                $localBillIdMap[$result['local_id']] = $result['bill_id'];
            }
        }

        $paymentResults = $this->syncPayments($validated['payments'] ?? [], $user, $ownerId, $localBillIdMap);
        $installmentResults = $this->syncInstallments($validated['installments'] ?? [], $user, $ownerId, $localBillIdMap);

        return response()->json([
            'bills' => [
                'synced' => count(array_filter($billResults, fn ($r) => $r['success'])),
                'results' => $billResults,
            ],
            'payments' => [
                'synced' => count(array_filter($paymentResults, fn ($r) => $r['success'])),
                'results' => $paymentResults,
            ],
            'installments' => [
                'synced' => count(array_filter($installmentResults, fn ($r) => $r['success'])),
                'results' => $installmentResults,
            ],
        ]);
    }

    private function syncBills(array $items, User $user): array
    {
        if ($items !== [] && $user->role === 'employee' && ! $user->hasPermission('create_bills')) {
            return $this->forbiddenResults($items);
        }

        $service = app(BillService::class);
        $results = [];

        foreach ($items as $item) {
            try {
                if (empty($item['client_uuid']) && ! empty($item['local_id'])) {
                    $item['client_uuid'] = $this->boundedKey($item['local_id']);
                }
                $result = $service->create($user, $item);

                $results[] = [
                    'local_id' => $item['local_id'],
                    'success' => true,
                    'bill_id' => $result['bill']->id,
                    'duplicate' => $result['duplicate'],
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'local_id' => $item['local_id'],
                    'success' => false,
                    'error' => $this->errorMessage($e),
                    'duplicate' => false,
                ];
            }
        }

        return $results;
    }

    private function syncPayments(array $items, User $user, int $ownerId, array $localBillIdMap = []): array
    {
        if ($items !== [] && $user->role === 'employee' && ! $user->hasPermission('manage_payments_receipts')) {
            return $this->forbiddenResults($items);
        }

        $results = [];

        foreach ($items as $item) {
            $clientUuid = $this->syncClientUuid($item);

            try {
                $duplicate = $this->existingPaymentByClientUuid($ownerId, $clientUuid);

                if ($duplicate) {
                    $results[] = [
                        'local_id' => $item['local_id'],
                        'success' => true,
                        'duplicate' => true,
                        'payment_id' => $duplicate->id,
                    ];
                    continue;
                }

                DB::transaction(function () use ($item, $ownerId, $localBillIdMap, $clientUuid) {
                    $customer = Customer::withoutGlobalScopes()
                        ->where('id', $item['customer_id'])
                        ->where('user_id', $ownerId)
                        ->firstOrFail();

                    $bill = $this->resolveBillReferenceForCustomer($item['bill_id'] ?? null, $ownerId, $localBillIdMap, $customer->id);
                    $amount = round((float) $item['amount'], 2);
                    $at = $this->paymentDate($item['payment_date'] ?? null);

                    if ($bill && $amount > 0) {
                        $summary = CustomerLedger::billSummary($bill);
                        if ($amount > $summary['due']) {
                            throw ValidationException::withMessages([
                                'amount' => __('receivables.validation.bill_payment_exceeds_due'),
                            ]);
                        }

                        CustomerLedger::receiveForBill($bill, $amount, $item['type'], $item['note'] ?? null, $at, $clientUuid);

                        return;
                    }

                    if ($amount > 0) {
                        CustomerLedger::receive($customer, $amount, $item['type'], $item['note'] ?? null, $at, null, CustomerLedger::KIND_PAYMENT, $clientUuid);

                        return;
                    }

                    if ($bill) {
                        throw ValidationException::withMessages([
                            'amount' => __('receivables.validation.bill_link_positive_only'),
                        ]);
                    }

                    CustomerLedger::adjust($customer, $amount, $item['type'], $item['note'] ?? null, $at, CustomerLedger::KIND_ADJUSTMENT, $clientUuid);
                });

                $created = $this->existingPaymentByClientUuid($ownerId, $clientUuid);

                $results[] = [
                    'local_id' => $item['local_id'],
                    'success' => true,
                    'duplicate' => false,
                    'payment_id' => $created?->id,
                ];
            } catch (QueryException $e) {
                if ($this->isClientUuidDuplicate($e)) {
                    $duplicate = $this->existingPaymentByClientUuid($ownerId, $clientUuid);
                    if ($duplicate) {
                        $results[] = [
                            'local_id' => $item['local_id'],
                            'success' => true,
                            'duplicate' => true,
                            'payment_id' => $duplicate->id,
                        ];

                        continue;
                    }
                }

                $results[] = $this->syncErrorResult($item, $e);
            } catch (\Throwable $e) {
                $results[] = $this->syncErrorResult($item, $e);
            }
        }

        return $results;
    }

    private function syncInstallments(array $items, User $user, int $ownerId, array $localBillIdMap = []): array
    {
        if (! $user->canAccessFeature('installments')) {
            return array_map(fn ($item) => [
                'local_id' => $item['local_id'],
                'success' => false,
                'error' => 'Installments feature not available on this account',
            ], $items);
        }

        if ($items !== [] && $user->role === 'employee' && ! $user->hasPermission('create_installments')) {
            return $this->forbiddenResults($items);
        }

        $results = [];

        foreach ($items as $item) {
            $clientUuid = $this->syncClientUuid($item);

            try {
                $duplicate = $this->existingInstallmentByClientUuid($ownerId, $clientUuid);

                if ($duplicate) {
                    $results[] = [
                        'local_id' => $item['local_id'],
                        'success' => true,
                        'duplicate' => true,
                        'installment_id' => $duplicate->id,
                    ];
                    continue;
                }

                DB::transaction(function () use ($item, $user, $ownerId, $localBillIdMap, $clientUuid) {
                    $customer = Customer::withoutGlobalScopes()
                        ->where('id', $item['customer_id'])
                        ->where('user_id', $ownerId)
                        ->firstOrFail();

                    $bill = $this->resolveBillReferenceForCustomer($item['bill_id'] ?? null, $ownerId, $localBillIdMap, $customer->id);
                    $initialPayment = round((float) ($item['initial_payment'] ?? 0), 2);

                    $plan = InstallmentPlan::create([
                        'user_id' => $ownerId,
                        'customer_id' => $customer->id,
                        'bill_id' => $bill?->id,
                        'client_uuid' => $clientUuid,
                        'total_amount' => $item['total_amount'],
                        'initial_payment' => $initialPayment,
                        'note' => $item['note'] ?? null,
                        'is_standalone' => $bill === null,
                        'created_by' => $user->id,
                    ]);

                    foreach (($item['payments'] ?? []) as $payment) {
                        if (empty($payment['due_date']) || empty($payment['amount'])) {
                            continue;
                        }

                        InstallmentPayment::create([
                            'installment_plan_id' => $plan->id,
                            'user_id' => $ownerId,
                            'amount' => $payment['amount'],
                            'due_date' => $payment['due_date'],
                            'note' => $payment['note'] ?? null,
                        ]);
                    }

                    if ($initialPayment <= 0) {
                        return;
                    }

                    if ($bill) {
                        CustomerLedger::receiveForBill(
                            $bill,
                            $initialPayment,
                            'cash',
                            __('receivables.installment_initial_payment_note', ['bill' => $bill->id]),
                            null,
                            $this->boundedKey($clientUuid . ':initial'),
                        );

                        return;
                    }

                    CustomerLedger::receive(
                        $customer,
                        $initialPayment,
                        'cash',
                        __('receivables.installment_initial_payment_general_note'),
                        null,
                        null,
                        CustomerLedger::KIND_PAYMENT,
                        $this->boundedKey($clientUuid . ':initial'),
                    );
                });

                $created = $this->existingInstallmentByClientUuid($ownerId, $clientUuid);

                $results[] = [
                    'local_id' => $item['local_id'],
                    'success' => true,
                    'duplicate' => false,
                    'installment_id' => $created?->id,
                ];
            } catch (QueryException $e) {
                if ($this->isClientUuidDuplicate($e)) {
                    $duplicate = $this->existingInstallmentByClientUuid($ownerId, $clientUuid);
                    if ($duplicate) {
                        $results[] = [
                            'local_id' => $item['local_id'],
                            'success' => true,
                            'duplicate' => true,
                            'installment_id' => $duplicate->id,
                        ];

                        continue;
                    }
                }

                $results[] = $this->syncErrorResult($item, $e);
            } catch (\Throwable $e) {
                $results[] = $this->syncErrorResult($item, $e);
            }
        }

        return $results;
    }

    private function resolveBillReferenceForCustomer(mixed $rawBillId, int $ownerId, array $localBillIdMap, int $customerId): ?Bill
    {
        if ($rawBillId === null || $rawBillId === '') {
            return null;
        }

        if (isset($localBillIdMap[$rawBillId])) {
            $rawBillId = $localBillIdMap[$rawBillId];
        }

        if (! is_numeric($rawBillId)) {
            throw ValidationException::withMessages([
                'bill_id' => __('receivables.validation.bill_reference_unresolved'),
            ]);
        }

        return Bill::withoutGlobalScopes()
            ->where('id', (int) $rawBillId)
            ->where('user_id', $ownerId)
            ->where('customer_id', $customerId)
            ->firstOr(function () {
                throw ValidationException::withMessages([
                    'bill_id' => __('receivables.validation.bill_reference_unresolved'),
                ]);
            });
    }

    private function paymentDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->setTime(now()->hour, now()->minute, now()->second);
    }

    private function errorMessage(\Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return collect($e->errors())->flatten()->first() ?? $e->getMessage();
        }

        return $e->getMessage();
    }

    private function forbiddenResults(array $items): array
    {
        return array_map(fn ($item) => [
            'local_id' => $item['local_id'] ?? null,
            'success' => false,
            'error' => 'Unauthorized',
        ], $items);
    }

    private function syncClientUuid(array $item): string
    {
        return $this->boundedKey($item['client_uuid'] ?? $item['local_id'] ?? '');
    }

    private function boundedKey(mixed $value): string
    {
        $key = trim((string) $value);
        if ($key === '') {
            return '';
        }

        return strlen($key) <= 64
            ? $key
            : hash('sha256', $key);
    }

    private function existingPaymentByClientUuid(int $ownerId, string $clientUuid): ?CustomerPayment
    {
        if ($clientUuid === '') {
            return null;
        }

        return CustomerPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('client_uuid', $clientUuid)
            ->first();
    }

    private function existingInstallmentByClientUuid(int $ownerId, string $clientUuid): ?InstallmentPlan
    {
        if ($clientUuid === '') {
            return null;
        }

        return InstallmentPlan::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('client_uuid', $clientUuid)
            ->first();
    }

    private function isClientUuidDuplicate(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return in_array((string) $e->getCode(), ['19', '23000', '23505'], true)
            || str_contains($message, 'customer_payments_user_id_client_uuid_unique')
            || str_contains($message, 'installment_plans_user_id_client_uuid_unique')
            || str_contains($message, 'unique constraint failed: customer_payments.user_id, customer_payments.client_uuid')
            || str_contains($message, 'unique constraint failed: installment_plans.user_id, installment_plans.client_uuid')
            || str_contains($message, 'duplicate entry');
    }

    private function syncErrorResult(array $item, \Throwable $e): array
    {
        $result = [
            'local_id' => $item['local_id'] ?? null,
            'success' => false,
            'error' => $this->errorMessage($e),
        ];

        if ($e instanceof ValidationException && array_key_exists('bill_id', $e->errors())) {
            $result['reason_code'] = 'bill_reference_unresolved';
        }

        return $result;
    }
}

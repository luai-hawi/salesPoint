<?php

namespace App\Http\Controllers;

use App\Models\InstallmentPlan;
use App\Models\InstallmentPayment;
use App\Models\InstallmentDismissal;
use App\Models\CustomerPayment;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InstallmentController extends Controller
{
    /**
     * Resolve the owner ID for data isolation.
     */
    private function ownerId(): int
    {
        $user = auth()->user();
        return $user->role === 'employee' ? $user->shop_owner_id : $user->id;
    }

    /**
     * Count of undismissed due payments for today (for nav badge).
     */
    private function dueCountQuery(int $ownerId): int
    {
        $userId = auth()->id();
        $today  = today()->toDateString();

        return InstallmentPayment::whereHas('plan', fn($q) => $q->where('user_id', $ownerId))
            ->where('is_paid', false)
            ->whereDate('due_date', '<=', $today)
            ->whereDoesntHave('dismissals', fn($q) => $q->where('user_id', $userId)->where('dismissed_for_date', $today))
            ->count();
    }

    /**
     * GET /installments
     */
    public function index(Request $request)
    {
        $user    = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('view_installments')) {
            abort(403);
        }

        $ownerId = $this->ownerId();
        $userId  = $user->id;
        $today   = today()->toDateString();

        $query = InstallmentPlan::where('user_id', $ownerId)
            ->with(['customer', 'bill', 'payments.dismissals', 'creator']);

        // --- Search ---
        if ($search = $request->input('search')) {
            $s = '%' . $search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('customer_name_override', 'like', $s)
                    ->orWhere('note', 'like', $s)
                    ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', $s))
                    ->orWhere('id', 'like', $s);
            });
        }

        // --- Status filter ---
        $status = $request->input('status', 'all');
        switch ($status) {
            case 'due_today':
                $query->whereHas('payments', fn($q) => $q->where('is_paid', false)->whereDate('due_date', $today));
                break;
            case 'overdue':
                $query->whereHas('payments', fn($q) => $q->where('is_paid', false)->whereDate('due_date', '<', $today));
                break;
            case 'upcoming':
                $query->whereHas('payments', fn($q) => $q->where('is_paid', false)->whereDate('due_date', '>', $today));
                break;
            case 'paid':
                $query->whereDoesntHave('payments', fn($q) => $q->where('is_paid', false));
                break;
            case 'standalone':
                $query->where('is_standalone', true);
                break;
        }

        // --- Date range ---
        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $plans = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Due payments for today (for the notification strip at top)
        $dueToday = InstallmentPayment::whereHas('plan', fn($q) => $q->where('user_id', $ownerId))
            ->where('is_paid', false)
            ->whereDate('due_date', '<=', $today)
            ->whereDoesntHave('dismissals', fn($q) => $q->where('user_id', $userId)->where('dismissed_for_date', $today))
            ->with(['plan.customer', 'plan'])
            ->orderBy('due_date')
            ->get();

        $customers = Customer::where('user_id', $ownerId)->orderBy('name')->get();

        return view('installments.index', compact('plans', 'dueToday', 'customers', 'status', 'today'));
    }

    /**
     * POST /installments  — standalone plan
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_installments')) {
            abort(403);
        }

        $ownerId = $this->ownerId();

        $data = $request->validate([
            'customer_id'            => 'nullable|exists:customers,id,user_id,' . $ownerId,
            'customer_name_override' => 'nullable|string|max:255',
            'total_amount'           => 'required|numeric|min:0.01',
            'initial_payment'        => 'nullable|numeric|min:0',
            'note'                   => 'nullable|string|max:1000',
            'payments'               => 'required|array|min:1',
            'payments.*.due_date'    => 'required|date',
            'payments.*.amount'      => 'required|numeric|min:0.01',
            'payments.*.note'        => 'nullable|string|max:500',
        ]);

        if (empty($data['customer_id']) && empty($data['customer_name_override'])) {
            return back()->withErrors(['customer_name_override' => __('messages.Debtor Name is required')])->withInput();
        }

        DB::transaction(function () use ($data, $user, $ownerId) {
            $plan = InstallmentPlan::create([
                'user_id'                => $ownerId,
                'customer_id'            => $data['customer_id'] ?? null,
                'customer_name_override' => $data['customer_name_override'] ?? null,
                'total_amount'           => $data['total_amount'],
                'initial_payment'        => $data['initial_payment'] ?? 0,
                'note'                   => $data['note'] ?? null,
                'is_standalone'          => true,
                'created_by'             => $user->id,
            ]);

            foreach ($data['payments'] as $p) {
                InstallmentPayment::create([
                    'installment_plan_id' => $plan->id,
                    'user_id'             => $ownerId,
                    'amount'              => $p['amount'],
                    'due_date'            => $p['due_date'],
                    'note'                => $p['note'] ?? null,
                ]);
            }
        });

        return redirect()->route('installments.index')
            ->with('success', __('messages.Installment plan created successfully'));
    }

    /**
     * POST /installments/from-bill  — plan linked to a bill
     */
    public function storeFromBill(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_installments')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $ownerId = $this->ownerId();

        $data = $request->validate([
            'bill_id'             => 'required|exists:bills,id,user_id,' . $ownerId,
            'customer_id'         => 'required|exists:customers,id,user_id,' . $ownerId,
            'total_amount'        => 'required|numeric|min:0.01',
            'initial_payment'     => 'nullable|numeric|min:0',
            'note'                => 'nullable|string|max:1000',
            'payments'            => 'nullable|array',
            'payments.*.due_date' => 'required_with:payments.*|date',
            'payments.*.amount'   => 'required_with:payments.*|numeric|min:0.01',
            'payments.*.note'     => 'nullable|string|max:500',
        ]);

        $initialPayment = (float) ($data['initial_payment'] ?? 0);

        DB::transaction(function () use ($data, $user, $ownerId, $initialPayment) {
            $plan = InstallmentPlan::create([
                'user_id'         => $ownerId,
                'customer_id'     => $data['customer_id'],
                'bill_id'         => $data['bill_id'],
                'total_amount'    => $data['total_amount'],
                'initial_payment' => $initialPayment,
                'note'            => $data['note'] ?? null,
                'is_standalone'   => false,
                'created_by'      => $user->id,
            ]);

            foreach (($data['payments'] ?? []) as $p) {
                InstallmentPayment::create([
                    'installment_plan_id' => $plan->id,
                    'user_id'             => $ownerId,
                    'amount'              => $p['amount'],
                    'due_date'            => $p['due_date'],
                    'note'                => $p['note'] ?? null,
                ]);
            }

            // Record the initial payment as a CustomerPayment
            if ($initialPayment > 0) {
                CustomerPayment::create([
                    'customer_id' => $data['customer_id'],
                    'amount'      => $initialPayment,
                    'type'        => 'cash',
                    'note'        => __('messages.payment_for_bill_note', ['bill_id' => $data['bill_id']]),
                    'user_id'     => $ownerId,
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => __('messages.Installment plan created successfully')]);
    }

    /**
     * PUT /installments/{plan}
     */
    public function update(Request $request, InstallmentPlan $plan)
    {
        $this->authorizeOwner($plan);

        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_installments')) {
            abort(403);
        }

        $data = $request->validate([
            'customer_name_override' => 'nullable|string|max:255',
            'total_amount'           => 'required|numeric|min:0.01',
            'initial_payment'        => 'nullable|numeric|min:0',
            'note'                   => 'nullable|string|max:1000',
        ]);

        $plan->update($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', __('messages.Installment plan updated successfully'));
    }

    /**
     * DELETE /installments/{plan}
     */
    public function destroy(InstallmentPlan $plan)
    {
        $this->authorizeOwner($plan);

        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('delete_installments')) {
            abort(403);
        }

        $plan->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', __('messages.Installment plan deleted successfully'));
    }

    /**
     * POST /installments/payments/{payment}/mark-paid
     */
    public function markPaid(InstallmentPayment $payment)
    {
        $this->authorizeOwnerPayment($payment);

        if ($payment->is_paid) {
            return response()->json(['error' => 'Already paid'], 422);
        }

        $user    = auth()->user();
        $ownerId = $this->ownerId();

        DB::transaction(function () use ($payment, $user, $ownerId) {
            $payment->update([
                'is_paid' => true,
                'paid_at' => now(),
                'paid_by' => $user->id,
            ]);

            // Record as CustomerPayment if linked to a customer
            $plan = $payment->plan()->with('customer', 'bill')->first();
            if ($plan && $plan->customer_id) {
                $billRef = $plan->bill_id ? " (#" . $plan->bill_id . ")" : '';
                CustomerPayment::create([
                    'customer_id' => $plan->customer_id,
                    'amount'      => $payment->amount,
                    'type'        => 'cash',
                    'note'        => __('messages.installment_payment_note', ['plan_id' => $plan->id]) . $billRef,
                    'user_id'     => $ownerId,
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => __('messages.Payment marked as paid')]);
    }

    /**
     * POST /installments/payments/{payment}/dismiss
     */
    public function dismissToday(InstallmentPayment $payment)
    {
        $this->authorizeOwnerPayment($payment);

        $user  = auth()->user();
        $today = today()->toDateString();

        InstallmentDismissal::firstOrCreate([
            'installment_payment_id' => $payment->id,
            'user_id'                => $user->id,
            'dismissed_for_date'     => $today,
        ]);

        return response()->json(['success' => true, 'message' => __('messages.Notification dismissed')]);
    }

    /**
     * POST /installments/dismiss-all-today
     */
    public function dismissAllToday(Request $request)
    {
        $user    = auth()->user();
        $ownerId = $this->ownerId();
        $today   = today()->toDateString();

        $duePayments = InstallmentPayment::whereHas('plan', fn($q) => $q->where('user_id', $ownerId))
            ->where('is_paid', false)
            ->whereDate('due_date', '<=', $today)
            ->whereDoesntHave('dismissals', fn($q) => $q->where('user_id', $user->id)->where('dismissed_for_date', $today))
            ->get();

        foreach ($duePayments as $payment) {
            InstallmentDismissal::firstOrCreate([
                'installment_payment_id' => $payment->id,
                'user_id'                => $user->id,
                'dismissed_for_date'     => $today,
            ]);
        }

        return response()->json(['success' => true, 'message' => __('messages.All notifications dismissed for today')]);
    }

    /**
     * PUT /installments/payments/{payment}
     */
    public function updatePayment(Request $request, InstallmentPayment $payment)
    {
        $this->authorizeOwnerPayment($payment);

        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_installments')) {
            abort(403);
        }

        $data = $request->validate([
            'due_date' => 'required|date',
            'amount'   => 'required|numeric|min:0.01',
            'note'     => 'nullable|string|max:500',
        ]);

        $payment->update($data);

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /installments/payments/{payment}
     */
    public function destroyPayment(InstallmentPayment $payment)
    {
        $this->authorizeOwnerPayment($payment);

        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('delete_installments')) {
            abort(403);
        }

        $payment->delete();

        return response()->json(['success' => true, 'message' => __('messages.Installment deleted successfully')]);
    }

    /**
     * GET /installments/due-count  — JSON badge count
     */
    public function dueCount()
    {
        $ownerId = $this->ownerId();
        return response()->json(['count' => $this->dueCountQuery($ownerId)]);
    }

    // ── Authorization helpers ─────────────────────────────────────────────

    private function authorizeOwner(InstallmentPlan $plan): void
    {
        if ($plan->user_id !== $this->ownerId()) {
            abort(403);
        }
    }

    private function authorizeOwnerPayment(InstallmentPayment $payment): void
    {
        $ownerId = $this->ownerId();
        if ($payment->user_id !== $ownerId) {
            abort(403);
        }
    }
}

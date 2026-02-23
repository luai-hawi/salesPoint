<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeePayment;
use Illuminate\Support\Facades\Auth;

class EmployeeController extends Controller
{
    /**
     * Get the owner ID - works for both shop owners and employees
     */
    private function getOwnerId()
    {
        $user = Auth::user();
        // If user is a shop owner, use their ID
        // If user is an employee, use their shop_owner_id
        return $user->role === 'employee' ? $user->shop_owner_id : $user->id;
    }

    public function index()
    {
        $ownerId = $this->getOwnerId();
        $employees = Employee::where('shop_owner_id', $ownerId)->paginate(10);
        return view('shopowner.employees.index', compact('employees'));
    }

    public function create()
    {
        return view('shopowner.employees.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'job_title'      => 'required|string|max:255',
            'monthly_salary' => 'required|numeric|min:0',
        ]);

        $ownerId = $this->getOwnerId();
        Employee::create([
            'shop_owner_id'  => $ownerId,
            'name'           => $request->name,
            'job_title'      => $request->job_title,
            'monthly_salary' => $request->monthly_salary,
        ]);

        return redirect()->route('shopowner.employees.index')->with('success', 'Employee created successfully.');
    }

    public function edit(Employee $employee)
    {
        $this->authorizeOwner($employee);
        return view('shopowner.employees.edit', compact('employee'));
    }

    public function update(Request $request, Employee $employee)
    {
        $this->authorizeOwner($employee);

        $request->validate([
            'name'           => 'required|string|max:255',
            'job_title'      => 'required|string|max:255',
            'monthly_salary' => 'required|numeric|min:0',
        ]);

        $employee->update($request->only('name', 'job_title', 'monthly_salary'));

        return redirect()->route('shopowner.employees.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        $this->authorizeOwner($employee);
        $employee->delete();

        return redirect()->route('shopowner.employees.index')->with('success', 'Employee deleted successfully.');
    }

    public function payments(Request $request, Employee $employee)
    {
        $this->authorizeOwner($employee);

        $query = $employee->payments()->latest('payment_date');

        // 🔍 Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('payment_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('payment_date', '<=', $request->to);
        }

        // ✅ Paginate instead of get()
        $payments = $query->paginate(10);

        if ($request->ajax()) {
            return view('shopowner.employees.partials.payments_table', compact('payments'))->render();
        }

        return view('shopowner.employees.payments', compact('employee', 'payments'));
    }


    public function storePayment(Request $request, Employee $employee)
    {
        $this->authorizeOwner($employee);

        $request->validate([
            'amount'       => 'required|numeric|min:1',
            'payment_date' => 'required|date',
        ]);

        EmployeePayment::create([
            'employee_id'  => $employee->id,
            'amount'       => $request->amount,
            'payment_date' => $request->payment_date,
        ]);

        return redirect()->route('shopowner.employees.payments', $employee->id)->with('success', 'Payment recorded successfully.');
    }
    public function destroyPayment($paymentId)
    {

        $payment = EmployeePayment::findOrFail($paymentId);
        $this->authorizeOwner($payment->employee);
        $payment->delete();

        return back()->with('success', 'Payment removed successfully.');
    }


    private function authorizeOwner($employee)
    {
        $ownerId = $this->getOwnerId();
        if ($employee->shop_owner_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }
    }
}

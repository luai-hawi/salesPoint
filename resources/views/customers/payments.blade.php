<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Payments for {{ $customer->name }}
        </h2>
    </x-slot>

    <div class="py-6 max-w-5xl mx-6">
        {{-- Add New Payment --}}
        <form method="POST" action="{{ route('customers.payments.store', $customer->id) }}" class="mb-6 flex gap-2">
            @csrf
            <input type="number" step="0.01" name="amount" placeholder="Amount"
                   class="border px-3 py-2 rounded w-32" required>
            <input type="text" name="note" placeholder="Note (optional)"
                   class="border px-3 py-2 rounded flex-1">
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Add Payment</button>
        </form>
        <h3>
    Balance: <span id="customer-balance" class="text-red-600 font-semibold">{{ number_format($customer->balance, 2) }}₪</span>
</h3>


        {{-- Payments Table --}}
        <table class="min-w-full bg-white border">
            <thead>
                <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                    <th class="px-4 py-2 text-left">ID</th>
                    <th class="px-4 py-2 text-left">Amount</th>
                    <th class="px-4 py-2 text-left">Note</th>
                    <th class="px-4 py-2 text-left">Date</th>
                    <th class="px-4 py-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $payment)
                <tr data-id="{{ $payment->id }}" class="border-t">
                    <td class="px-4 py-2">{{ $payment->id }}</td>
                    <td class="px-4 py-2">
                        <input type="number" step="0.01" class="edit-amount border px-2 py-1 rounded w-24"
                               value="{{ $payment->amount }}"
                               data-old="{{ $payment->amount }}">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" class="edit-note border px-2 py-1 rounded w-full"
                               value="{{ $payment->note }}">
                    </td>
                    <td class="px-4 py-2">{{ $payment->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-2 flex gap-2">
                        <button type="button" class="save-payment bg-green-500 text-white px-3 py-1 rounded">Save</button>
                        <button type="button" class="delete-payment bg-red-500 text-white px-3 py-1 rounded">Delete</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

   <script>
    function getBalance() {
        return parseFloat(document.getElementById('customer-balance').innerText.replace('₪','')) || 0;
    }

    function setBalance(value) {
        document.getElementById('customer-balance').innerText = value.toFixed(2) + '₪';
    }

    // Handle Save Payment (Update)
    document.querySelectorAll('.save-payment').forEach(btn => {
        btn.addEventListener('click', function () {
            const row = this.closest('tr');
            const id = row.dataset.id;
            const amountInput = row.querySelector('.edit-amount');
            const newAmount = parseFloat(amountInput.value);
            const oldAmount = parseFloat(amountInput.getAttribute('data-old') || newAmount);

            const diff = newAmount - oldAmount;
            amountInput.setAttribute('data-old', newAmount); // store new as old for next update

            // Update balance immediately
            setBalance(getBalance() + diff);

            const note = row.querySelector('.edit-note').value;

            fetch(`/payments/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ amount: newAmount, note })
            })
            .then(res => {
                if(!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(() => {
                row.style.backgroundColor = '#d1fae5';
                setTimeout(() => row.style.backgroundColor = '', 700);
            })
            .catch(err => console.error('Update failed:', err));
        });
    });

    // Handle Delete Payment
    document.querySelectorAll('.delete-payment').forEach(btn => {
        btn.addEventListener('click', function () {
            if(!confirm('Are you sure you want to delete this payment?')) return;

            const row = this.closest('tr');
            const id = row.dataset.id;
            const amount = parseFloat(row.querySelector('.edit-amount').value);

            // Update balance immediately (subtracting payment)
            setBalance(getBalance() - amount);

            fetch(`/payments/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                }
            })
            .then(res => {
                if(!res.ok) throw new Error('Network error while deleting');
                row.remove(); // remove row instantly
            })
            .catch(err => console.error('Delete failed:', err));
        });
    });

    // Handle Add Payment Form Submit
    const addForm = document.querySelector('form[action*="payments"]');
    if (addForm) {
        addForm.addEventListener('submit', function () {
            const amount = parseFloat(this.querySelector('input[name="amount"]').value);
            setBalance(getBalance() + amount); // increase balance immediately
        });
    }
</script>

</x-app-layout>

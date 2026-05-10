<?php
// App/Models/Supplier.php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'balance',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseBills()
    {
        return $this->hasMany(PurchaseBill::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function getLastPurchaseBillData($userId = null)
    {
        $query = $this->purchaseBills()->latest('purchase_date');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $lastBill = $query->first();

        return [
            'amount' => $lastBill ? $lastBill->total_amount : 0,
            'bill_id' => $lastBill ? $lastBill->id : null,
            'date' => $lastBill ? $lastBill->purchase_date->format('M d, Y') : null
        ];
    }

    public function getTotalPurchases($userId = null)
    {
        $query = $this->purchaseBills();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->sum('total_amount');
    }

    public function getTotalPayments($userId = null)
    {
        $query = $this->payments();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->sum('amount');
    }
}

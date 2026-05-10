<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory, BelongsToTenant;

    // Fields that can be mass-assigned
    protected $fillable = [
        'name',
        'phone',
        'balance', // positive = owes us, negative = we owe them (optional)
        'user_id', // the user who owns this customer
    ];


    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class);
    }
    public function getLastBillAmount($userId = null)
    {
        $query = $this->bills()->latest('created_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $lastBill = $query->first();

        return $lastBill ? $lastBill->total_price : 0;
    }

    public function getLastBillData($userId = null)
    {
        $query = $this->bills()->latest('created_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $lastBill = $query->first();

        return [
            'amount' => $lastBill ? $lastBill->total_price : 0,
            'bill_id' => $lastBill ? $lastBill->id : null,
            'date' => $lastBill ? $lastBill->created_at->format('M d, Y') : null
        ];
    }
}

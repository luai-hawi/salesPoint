<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerPayment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'customer_id',
        'bill_id',
        'client_uuid',
        'amount',
        'type',
        'kind',
        'note',
        'user_id', // the user who owns this payment
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}

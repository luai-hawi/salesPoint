<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantOrder extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'user_id',
        'table_id',
        'order_type',
        'status',
        'label',
        'guests',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_address',
        'cart',
        'sent_snapshot',
        'open_table_key',
        'pending_bill_client_uuid',
        'total',
        'opened_by',
        'bill_id',
        'closed_at',
        'notes',
        'cancel_reason',
    ];

    protected $casts = [
        'cart' => 'array',
        'sent_snapshot' => 'array',
        'guests' => 'integer',
        'total' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    public function table()
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function tickets()
    {
        return $this->hasMany(KitchenTicket::class, 'order_id');
    }

    public function latestTicket()
    {
        return $this->hasOne(KitchenTicket::class, 'order_id')->latestOfMany();
    }
}

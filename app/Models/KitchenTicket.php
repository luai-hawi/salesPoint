<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KitchenTicket extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'user_id',
        'order_id',
        'number',
        'local_service_date',
        'items',
        'status',
        'priority',
        'station',
        'created_by',
        'sent_at',
        'started_at',
        'ready_at',
        'served_at',
        'cancelled_at',
        'client_uuid',
        'notes',
        'cancel_reason',
    ];

    protected $casts = [
        'items' => 'array',
        'sent_at' => 'datetime',
        'started_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(RestaurantOrder::class, 'order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeldBill extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'created_by',
        'label',
        'customer_id',
        'customer_name',
        'table_label',
        'items_count',
        'total',
        'payload',
        'client_uuid',
    ];

    protected $casts = [
        'payload' => 'array',
        'total' => 'decimal:2',
        'items_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

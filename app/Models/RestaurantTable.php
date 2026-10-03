<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantTable extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'user_id',
        'name',
        'zone',
        'seats',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'seats' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function orders()
    {
        return $this->hasMany(RestaurantOrder::class, 'table_id');
    }

    public function openOrder()
    {
        return $this->hasOne(RestaurantOrder::class, 'table_id')->where('status', 'open');
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A place (circle on the map) where staff are allowed to check in.
 */
class AttendanceLocation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'name', 'latitude', 'longitude', 'radius_m', 'is_active'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_m' => 'integer',
        'is_active' => 'boolean',
    ];
}
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeave extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
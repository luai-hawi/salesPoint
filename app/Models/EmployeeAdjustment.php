<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Bonus / deduction / penalty applied to one month of a staff member's salary.
 */
class EmployeeAdjustment extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
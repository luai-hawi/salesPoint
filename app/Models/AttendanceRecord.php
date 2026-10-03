<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One working session of a staff member (check-in … check-out).
 * status: open | closed | needs_review | approved | rejected
 */
class AttendanceRecord extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'work_date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_out_recorded_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'check_in_lat' => 'float',
        'check_in_lng' => 'float',
        'check_out_lat' => 'float',
        'check_out_lng' => 'float',
        'check_in_inside' => 'boolean',
        'check_out_inside' => 'boolean',
        'check_out_remote' => 'boolean',
        'minutes_worked' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function checkInLocation()
    {
        return $this->belongsTo(AttendanceLocation::class, 'check_in_location_id');
    }

    public function checkOutLocation()
    {
        return $this->belongsTo(AttendanceLocation::class, 'check_out_location_id');
    }

    public function isOpen(): bool
    {
        return $this->check_out_at === null;
    }
}
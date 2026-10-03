<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DayClosing extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'closing_date',
        'expected_cash',
        'counted_cash',
        'variance',
        'notes',
        'snapshot',
        'closed_by',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'variance' => 'decimal:2',
        'snapshot' => 'array',
    ];

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CapitalEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = ['amount', 'note', 'entry_date', 'user_id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'entry_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

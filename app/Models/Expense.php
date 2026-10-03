<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use BelongsToTenant;

    protected $fillable = ['title', 'category', 'amount', 'expense_date', 'notes', 'user_id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

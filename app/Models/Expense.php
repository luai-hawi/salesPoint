<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['title', 'amount', 'expense_date', 'notes', 'user_id'];


    public function user()
{
    return $this->belongsTo(User::class);
}
}

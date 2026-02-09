<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalEntry extends Model
{
    protected $fillable = ['amount', 'note', 'entry_date', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

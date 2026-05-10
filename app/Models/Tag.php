<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['name', 'price', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

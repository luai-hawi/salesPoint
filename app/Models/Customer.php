<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
   


   use HasFactory;

    // Fields that can be mass-assigned
    protected $fillable = [
        'name',
        'phone',
        'balance', // positive = owes us, negative = we owe them (optional)
        'user_id', // the user who owns this customer
    ];


    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

   public function payments()
    {
        return $this->hasMany(CustomerPayment::class);
    }

}

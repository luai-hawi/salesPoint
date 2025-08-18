<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_owner_id',
        'name',
        'job_title',
        'monthly_salary',
    ];

    public function shopOwner()
    {
        return $this->belongsTo(User::class, 'shop_owner_id');
    }

    public function remainingSalary()
    {
        return $this->monthly_salary - $this->amount_taken;
    }
    public function payments()
{
    return $this->hasMany(EmployeePayment::class);
}

public function paidThisMonth()
{
    return $this->payments()
        ->whereYear('payment_date', now()->year)
        ->whereMonth('payment_date', now()->month)
        ->sum('amount');
}

public function remainingThisMonth()
{
    return $this->monthly_salary - $this->paidThisMonth();
}

}

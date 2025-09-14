<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'total_amount',
        'notes',
        'reference_number',
        'purchase_date',
        'user_id',
        'created_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'purchase_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'purchase_bill_product')
            ->withPivot('quantity', 'unit_cost', 'total_cost')
            ->withTimestamps();
    }
}
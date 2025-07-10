<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($purchase) {
            if (!$purchase->purchase_number) {
                $latestId = static::where('business_id', $purchase->business_id)->latest('id')->value('id') ?? 0;
                $purchase->purchase_number = 'C-' . str_pad($latestId + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}

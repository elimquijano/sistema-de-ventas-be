<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($sale) {
            $sale->uuid = Str::uuid();
            if (!$sale->sale_number) {
                $latestId = static::where('business_id', $sale->business_id)->latest('id')->value('id') ?? 0;
                $sale->sale_number = 'V-' . str_pad($latestId + 1, 6, '0', STR_PAD_LEFT);
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
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }
    
    public function credit()
    {
        return $this->hasOne(Credit::class);
    }
    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }
}

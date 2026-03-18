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
                $latestSale = static::where('business_id', $sale->business_id)->latest('id')->first();
                $nextNumber = 1;
                if ($latestSale && preg_match('/V-(\d+)/', $latestSale->sale_number, $matches)) {
                    $nextNumber = (int)$matches[1] + 1;
                }
                $sale->sale_number = 'V-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
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
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
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

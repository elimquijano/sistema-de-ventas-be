<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $appends = ['products_count', 'services_count', 'sales_count'];

    public function getProductsCountAttribute()
    {
        return $this->products()->count();
    }

    public function getServicesCountAttribute()
    {
        return $this->services()->count();
    }

    public function getSalesCountAttribute()
    {
        return $this->sales()->count();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function services()
    {
        return $this->hasMany(Service::class);
    }
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
    public function credits()
    {
        return $this->hasMany(Credit::class);
    }
    public function categories()
    {
        return $this->hasMany(Category::class);
    }
    public function cashRegisters()
    {
        return $this->hasMany(CashRegister::class);
    }
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function assetLoans()
    {
        return $this->hasMany(AssetLoan::class);
    }

    public function salaryAdvances()
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }
}

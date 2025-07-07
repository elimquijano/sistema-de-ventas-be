<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = ['expense_date' => 'date'];
    protected $appends = ['receipt_url'];

    protected static function boot()
    {
        parent::boot();
        static::deleting(function ($expense) {
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
        });
    }

    public function getReceiptUrlAttribute()
    {
        return $this->receipt_path ? Storage::disk('public')->url($this->receipt_path) : null;
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

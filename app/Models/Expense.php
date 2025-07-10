<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    use HasFactory;

    // It's better to use $fillable to be explicit about what can be mass-assigned
    protected $fillable = [
        'description',
        'amount',
        'expense_date',
        'category_id',
        'business_id',
        'created_by',
        'receipt_path',
        'notes', // Add notes here
    ];

    protected $casts = ['expense_date' => 'date'];
    protected $appends = ['receipt_url'];

    // No need for the deleting boot method if we handle it in the controller's destroy method.
    // This keeps the model cleaner.

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

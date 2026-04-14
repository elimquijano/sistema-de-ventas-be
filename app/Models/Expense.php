<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    /**
     * Solo auditamos lo vital del gasto.
     */
    protected $auditInclude = [
        'description', 
        'amount', 
        'expense_date', 
        'category_id'
    ];

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

    /**
     * Resolución de metadatos para auditoría.
     */
    public function auditMetadata($values)
    {
        if (isset($values['category_id'])) {
            $cat = Category::find($values['category_id']);
            return ['category_name' => $cat ? $cat->name : "Categoría #{$values['category_id']}"];
        }
        return null;
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleItem extends Model
{
    use HasFactory, Auditable, SoftDeletes;
    protected $guarded = [];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
    
    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Define el padre para la línea de tiempo.
     */
    public function getAuditParent()
    {
        return $this->sale;
    }

    public function auditMetadata($values)
    {
        return [
            'item_name' => $values['item_name'] ?? $this->item_name,
            'quantity' => $values['quantity'] ?? $this->quantity,
        ];
    }
}

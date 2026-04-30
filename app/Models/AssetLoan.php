<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetLoan extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'business_id',
        'asset_id',
        'borrower_name',
        'quantity',
        'loan_date',
        'due_date',
        'return_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'loan_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Proporciona metadatos para el log de auditoría.
     */
    public function auditMetadata($values)
    {
        $asset = $this->asset ?? Asset::find($values['asset_id'] ?? $this->asset_id);
        
        return [
            'asset_name' => $asset ? $asset->name : 'Bien desconocido',
            'borrower_name' => $values['borrower_name'] ?? $this->borrower_name,
        ];
    }
}

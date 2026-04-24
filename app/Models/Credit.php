<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Credit extends Model
{
    use HasFactory, Auditable, SoftDeletes;
    protected $guarded = [];
    protected $casts = ['due_date' => 'date'];

    /**
     * Campos a incluir en la auditoría.
     */
    protected $auditInclude = [
        'customer_name',
        'total_amount',
        'paid_amount',
        'pending_amount',
        'status',
        'due_date'
    ];

    /**
     * Define el padre de la auditoría (la venta).
     */
    public function getAuditParent()
    {
        return $this->sale;
    }

    /**
     * Metadatos para las notificaciones.
     */
    public function auditMetadata($newValues)
    {
        return [
            'client_name' => $this->customer_name,
            'sale_number' => $this->sale ? $this->sale->sale_number : null,
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}

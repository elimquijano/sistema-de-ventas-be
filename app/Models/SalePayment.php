<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class SalePayment extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    protected $auditInclude = ['amount', 'payment_method', 'reference'];

    protected $guarded = [];

    protected $appends = ['payment_image_url'];

    public function getPaymentImageUrlAttribute(): ?string
    {
        return $this->payment_image
            ? Storage::disk('public')->url($this->payment_image)
            : null;
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
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
            'method' => $values['payment_method'] ?? $this->payment_method,
            'amount' => $values['amount'] ?? $this->amount,
        ];
    }
}

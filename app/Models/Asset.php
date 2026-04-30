<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'type',
        'total_quantity',
        'available_quantity',
        'unit_price',
        'status',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetReconciliationProductMapping extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'units_per_sale' => 'integer',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

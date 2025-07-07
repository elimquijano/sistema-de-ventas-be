<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = ['duration' => 'integer'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function saleItems()
    {
        return $this->morphMany(SaleItem::class, 'item');
    }
}

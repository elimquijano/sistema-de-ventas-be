<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, Auditable;
    protected $guarded = [];
    protected $appends = ['image_url'];

    protected static function boot()
    {
        parent::boot();
        static::deleting(function ($product) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
        });
    }

    public function getImageUrlAttribute()
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

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

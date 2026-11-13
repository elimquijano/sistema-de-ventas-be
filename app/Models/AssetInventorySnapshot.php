<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetInventorySnapshot extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'counted_at' => 'datetime',
        'confirmed_unbalanced' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function baseSnapshot()
    {
        return $this->belongsTo(self::class, 'base_snapshot_id');
    }

    public function items()
    {
        return $this->hasMany(AssetInventorySnapshotItem::class, 'snapshot_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

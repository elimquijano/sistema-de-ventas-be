<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetInventorySnapshotItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'calculation' => 'array',
    ];

    public function snapshot()
    {
        return $this->belongsTo(AssetInventorySnapshot::class, 'snapshot_id');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}

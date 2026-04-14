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

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}

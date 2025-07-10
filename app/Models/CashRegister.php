<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasFactory;
    protected $guarded = []; // Asegura que todos los campos puedan ser asignados masivamente
    protected $casts = ['opened_at' => 'datetime', 'closed_at' => 'datetime'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollPayment extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'user_id',
        'business_id',
        'expense_id',
        'base_salary',
        'advances_discounted',
        'final_payment',
        'start_date',
        'end_date',
        'payment_date',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'payment_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * Proporciona metadatos para el log de auditoría.
     */
    public function auditMetadata($values)
    {
        $user = $this->user ?? User::find($values['user_id'] ?? $this->user_id);
        return [
            'user_name' => $user ? $user->full_name : 'Empleado desconocido',
        ];
    }
}

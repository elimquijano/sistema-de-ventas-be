<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryAdvance extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'user_id',
        'business_id',
        'amount',
        'date',
        'description',
        'status',
        'expense_id',
        'payroll_payment_id',
    ];

    protected $casts = [
        'date' => 'date',
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

    public function payrollPayment()
    {
        return $this->belongsTo(PayrollPayment::class);
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

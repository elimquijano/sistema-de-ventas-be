<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Loan extends Model
{
    use HasFactory, Auditable;

    /**
     * Rastreamos solo los hitos importantes del préstamo.
     */
    protected $auditInclude = [
        'description', 
        'amount', 
        'paid_amount', 
        'pending_amount', 
        'status'
    ];

    protected $fillable = [
        'description',
        'amount',
        'loan_date',
        'due_date',
        'paid_amount',
        'pending_amount',
        'status',
        'business_id',
        'created_by',
    ];

    protected $casts = ['loan_date' => 'date', 'due_date' => 'date'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

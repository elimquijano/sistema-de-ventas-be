<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPayrollConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_id',
        'base_salary',
        'payment_frequency',
        'start_date',
        'work_schedule',
    ];

    protected $casts = [
        'start_date' => 'date',
        'work_schedule' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}

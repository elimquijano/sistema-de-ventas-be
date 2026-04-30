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
        'work_schedule',
    ];

    protected $casts = [
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

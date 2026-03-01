<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone',
        'business_id',
        'created_by_user_id',
        'latitude',
        'longitude',
        'address',
        'address_detail',
        'image',
        'route',
        'estimated_time',
        'approximate_distance',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'route' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['image_path'];

    /**
     * Get the full URL of the client's image.
     */
    public function getImagePathAttribute()
    {
        return $this->image ? url(Storage::url($this->image)) : null;
    }

    /**
     * Get the business that owns the client.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the user who created the client.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

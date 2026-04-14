<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * Campos a incluir en la auditoría (mínimo necesario).
     * Evitamos guardar UUIDs, notas largas, etc. en el log.
     */
    protected $auditInclude = [
        'sale_number', 
        'total_amount', 
        'status', 
        'rider_id', 
        'client_id'
    ];

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($sale) {
            $sale->uuid = Str::uuid();
            if (!$sale->sale_number) {
                $latestSale = static::where('business_id', $sale->business_id)->latest('id')->first();
                $nextNumber = 1;
                if ($latestSale && preg_match('/V-(\d+)/', $latestSale->sale_number, $matches)) {
                    $nextNumber = (int)$matches[1] + 1;
                }
                $sale->sale_number = 'V-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }
    
    public function credit()
    {
        return $this->hasOne(Credit::class);
    }
    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * Resolución de metadatos para auditoría.
     */
    public function auditMetadata($values)
    {
        $meta = [];
        if (isset($values['rider_id'])) {
            $rider = User::find($values['rider_id']);
            $meta['rider_name'] = $rider ? $rider->full_name : "Motorizado #{$values['rider_id']}";
        }
        if (isset($values['client_id'])) {
            $client = Client::find($values['client_id']);
            $meta['client_name'] = $client ? $client->name : "Cliente #{$values['client_id']}";
        }
        return count($meta) ? $meta : null;
    }
}

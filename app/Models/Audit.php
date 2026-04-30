<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class Audit extends Model
{
    use Prunable;

    protected $guarded = [];

    protected $appends = ['description'];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'metadata' => 'json',
    ];

    /**
     * Define qué registros son "podables" (borrables automáticamente).
     * Esto evita que la BD explote en tamaño.
     */
    public function prunable()
    {
        return static::where(function($query) {
            // Borrar actualizaciones (updated) después de 6 meses (son las que más pesan)
            $query->where('event', 'updated')
                  ->where('created_at', '<=', now()->subMonths(6));
        })->orWhere(function($query) {
            // Borrar todo lo demás después de 1 año (creaciones, eliminaciones, etc)
            $query->where('created_at', '<=', now()->subYear());
        });
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Genera una descripción legible y descriptiva.
     */
    public function getDescriptionAttribute()
    {
        $class = class_basename($this->auditable_type);
        $meta = $this->metadata ?? [];

        switch ($this->event) {
            case 'created':
                if ($class === 'Sale') {
                    $client = $meta['client_name'] ?? 'Cliente';
                    $rider = isset($meta['rider_name']) ? " asignado a {$meta['rider_name']}" : "";
                    return "Creó el pedido para {$client}{$rider}.";
                }
                if ($class === 'SalePayment') {
                    $method = $meta['method'] ?? 'Desconocido';
                    $amount = $meta['amount'] ?? '0.00';
                    return "Registró un pago de S/ {$amount} vía {$method}.";
                }
                if ($class === 'Expense') {
                    $amount = $this->new_values['amount'] ?? '0.00';
                    $desc = $this->new_values['description'] ?? 'Sin descripción';
                    $cat = $meta['category_name'] ?? 'General';
                    return "Registró un nuevo gasto de S/ {$amount} en {$cat}: {$desc}.";
                }
                if ($class === 'Loan') {
                    $amount = $this->new_values['amount'] ?? '0.00';
                    $desc = $this->new_values['description'] ?? 'Sin descripción';
                    return "Inició un préstamo por S/ {$amount}: {$desc}.";
                }
                if ($class === 'Credit') {
                    $amount = $this->new_values['total_amount'] ?? '0.00';
                    $client = $this->new_values['customer_name'] ?? 'Cliente';
                    return "Generó un crédito de S/ {$amount} para el cliente {$client}.";
                }
                if ($class === 'AssetLoan') {
                    $asset = $meta['asset_name'] ?? 'bien';
                    $borrower = $this->new_values['borrower_name'] ?? 'alguien';
                    $qty = $this->new_values['quantity'] ?? '1';
                    return "Prestó {$qty} unidad(es) de '{$asset}' a '{$borrower}'.";
                }
                if ($class === 'SalaryAdvance') {
                    $amount = $this->new_values['amount'] ?? '0.00';
                    $user = $meta['user_name'] ?? 'un empleado';
                    return "Entregó un adelanto de S/ {$amount} a {$user}.";
                }
                if ($class === 'PayrollPayment') {
                    $amount = $this->new_values['final_payment'] ?? '0.00';
                    $user = $meta['user_name'] ?? 'un empleado';
                    return "Realizó el pago de planilla de S/ {$amount} a {$user}.";
                }
                if ($class === 'Asset') {
                    $name = $this->new_values['name'] ?? 'Activo';
                    $qty = $this->new_values['total_quantity'] ?? '0';
                    return "Registró el ingreso de un nuevo activo al inventario: \"{$name}\" con una cantidad inicial de {$qty} unidades.";
                }
                return "Creó " . $this->getFriendlyModelName();

            case 'updated':
                if ($class === 'Asset' && isset($this->new_values['available_quantity'])) {
                    $old = $this->old_values['available_quantity'] ?? 0;
                    $new = $this->new_values['available_quantity'];
                    $diff = abs($new - $old);
                    $action = ($new < $old) ? "Salida" : "Reingreso";
                    $reason = ($new < $old) ? "por préstamo" : "por devolución";
                    return "{$action} de inventario: {$diff} unidad(es) {$reason}. Cantidad actual: {$new}.";
                }

                if ($class === 'Sale' && isset($this->new_values['status'])) {
                    $status = $this->new_values['status'];
                    $labels = ['completed' => 'Completada/Entregada', 'pending' => 'Pendiente', 'cancelled' => 'Cancelada', 'debt' => 'Deuda'];
                    return "Cambió el estado del pedido a: " . ($labels[$status] ?? $status);
                }
                
                if ($class === 'AssetLoan' && isset($this->new_values['status'])) {
                    $status = $this->new_values['status'];
                    $labels = ['returned' => 'Devuelto', 'lost' => 'Perdido', 'damaged' => 'Dañado', 'loaned' => 'Prestado'];
                    $asset = $meta['asset_name'] ?? 'bien';
                    return "Actualizó el estado del préstamo de '{$asset}' a: " . ($labels[$status] ?? $status);
                }

                if ($class === 'Loan' && isset($this->new_values['paid_amount'])) {
                    $paid = $this->new_values['paid_amount'] - ($this->old_values['paid_amount'] ?? 0);
                    $total_paid = $this->new_values['paid_amount'];
                    $pending = $this->new_values['pending_amount'] ?? '0.00';
                    $desc = $this->metadata['description'] ?? 'préstamo';
                    return "Registró un abono al préstamo '{$desc}'. Monto pagado hoy: S/ {$paid}, total pagado: S/ {$total_paid}, pendiente: S/ {$pending}.";
                }

                if ($class === 'Credit' && isset($this->new_values['paid_amount'])) {
                    $paid = $this->new_values['paid_amount'] - ($this->old_values['paid_amount'] ?? 0);
                    $total_paid = $this->new_values['paid_amount'];
                    $pending = $this->new_values['pending_amount'] ?? '0.00';
                    $client = $this->metadata['client_name'] ?? 'el cliente';
                    return "Registró un pago de crédito de S/ {$paid} para {$client}. Total pagado: S/ {$total_paid}, pendiente: S/ {$pending}.";
                }
                
                $changes = [];
                foreach ($this->new_values ?? [] as $key => $value) {
                    $old = $this->old_values[$key] ?? 'nulo';
                    $changes[] = "cambió " . $this->formatFieldName($key) . " de '{$old}' a '{$value}'";
                }
                return "Actualizó " . $this->getFriendlyModelName() . ": " . implode(', ', $changes);

            case 'deleted':
                return "Eliminó " . $this->getFriendlyModelName();
            default:
                return $this->event;
        }
    }

    protected function getFriendlyModelName()
    {
        $class = class_basename($this->auditable_type);
        $names = [
            'Sale' => 'la venta',
            'Product' => 'el producto',
            'Service' => 'el servicio',
            'Client' => 'el cliente',
            'Expense' => 'el gasto',
            'Loan' => 'el préstamo',
            'Credit' => 'el crédito',
            'User' => 'el usuario',
            'Role' => 'el rol',
            'Permission' => 'el permiso',
            'Asset' => 'el activo',
            'AssetLoan' => 'el préstamo de bien',
            'SalaryAdvance' => 'el adelanto de sueldo',
            'PayrollPayment' => 'el pago de planilla',
        ];

        return $names[$class] ?? strtolower($class);
    }

    protected function formatFieldName($key)
    {
        $fields = [
            'status' => 'el estado',
            'total_amount' => 'el monto total',
            'customer_name' => 'el nombre del cliente',
            'rider_id' => 'el repartidor',
            'delivery_address' => 'la dirección de entrega',
            'delivery_phone' => 'el teléfono de entrega',
            'delivery_notes' => 'las notas de entrega',
            'scheduled_at' => 'la fecha programada',
            'stock' => 'el stock',
            'available_quantity' => 'la cantidad disponible',
            'total_quantity' => 'la cantidad total',
            'price' => 'el precio',
            'name' => 'el nombre',
            'description' => 'la descripción',
            'address' => 'la dirección',
            'phone' => 'el teléfono',
            'email' => 'el correo electrónico',
        ];

        return $fields[$key] ?? str_replace('_', ' ', $key);
    }
}


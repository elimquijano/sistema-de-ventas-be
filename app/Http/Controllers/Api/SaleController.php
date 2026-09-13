<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Service;
use App\Models\CashRegister;
use App\Models\SalePayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Models\Client;
use App\Models\Category;
use App\Models\Expense;
use App\Notifications\OrderAssignedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SaleController extends Controller
{
    /**
     * Crear un pedido rápido para delivery.
     * Busca el cliente por teléfono o lo crea si es nuevo.
     * Permite sobreescribir el monto total del producto.
     */
    public function quickOrder(Request $request)
    {
        $business = Auth::user()->business;
        $validated = $request->validate([
            'phone' => 'required|string|size:9',
            'customer_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            // Se mantiene product_id/quantity por compatibilidad con clientes antiguos.
            'product_id' => 'required_without:items|nullable|exists:products,id',
            'quantity' => 'required_without:items|nullable|integer|min:1',
            'items' => 'required_without:product_id|nullable|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.type' => 'required|string|in:product,service',
            'items.*.quantity' => 'required|integer|min:1',
            'total_amount' => 'nullable|numeric|min:0',
            'rider_id' => 'required|exists:users,id',
            'notes' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $sale = DB::transaction(function () use ($validated, $business) {
            // 1. Buscar o Crear Cliente (Basado en teléfono Y dirección para permitir múltiples ubicaciones)
            $client = Client::where('phone', $validated['phone'])
                ->where('address', $validated['address'] ?? null)
                ->where('business_id', $business->id)
                ->first();

            if (!$client) {
                $client = Client::create([
                    'phone' => $validated['phone'],
                    'name' => $validated['customer_name'] ?? 'Cliente (' . $validated['phone'] . ')',
                    'address' => $validated['address'] ?? null,
                    'address_detail' => $validated['notes'] ?? null,
                    'latitude' => $validated['latitude'] ?? 0,
                    'longitude' => $validated['longitude'] ?? 0,
                    'business_id' => $business->id,
                    'created_by_user_id' => Auth::id(),
                ]);
            }

            // 2. Preparar uno o varios productos/servicios.
            $requestedItems = $validated['items'] ?? [[
                'id' => $validated['product_id'],
                'type' => 'product',
                'quantity' => $validated['quantity'],
            ]];

            $preparedItems = collect($requestedItems)->map(function ($itemData) use ($business) {
                $modelClass = $itemData['type'] === 'product' ? Product::class : Service::class;
                $item = $modelClass::where('business_id', $business->id)->findOrFail($itemData['id']);

                return compact('itemData', 'modelClass', 'item');
            });

            // Agrupar cantidades evita aprobar dos líneas del mismo producto por encima del stock.
            $preparedItems->where('modelClass', Product::class)
                ->groupBy(fn ($prepared) => $prepared['item']->id)
                ->each(function ($lines) {
                    $product = $lines->first()['item'];
                    $quantity = $lines->sum(fn ($line) => $line['itemData']['quantity']);
                    if ($product->stock < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => ['Stock insuficiente para ' . $product->name . ' (Disponible: ' . $product->stock . ')']
                        ]);
                    }
                });

            $catalogTotal = $preparedItems->sum(fn ($prepared) =>
                $prepared['item']->price * $prepared['itemData']['quantity']
            );
            $totalAmount = $validated['total_amount'] ?? $catalogTotal;

            // 3. Crear la Venta (Borrador/Pending)
            $scheduledAt = $validated['scheduled_at'] ?? now();
            $sale = $business->sales()->create([
                'customer_name' => $client->name,
                'client_id' => $client->id,
                'rider_id' => $validated['rider_id'],
                'delivery_address' => $validated['address'] ?? $client->address,
                'delivery_phone' => $client->phone,
                'delivery_notes' => $validated['notes'] ?? null,
                'is_delivery' => true,
                'scheduled_at' => $scheduledAt,
                'created_at' => $scheduledAt, // Usar la fecha programada como fecha de creación para sincronización
                'created_by' => Auth::id(),
                'total_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            // 4. Crear los ítems. Si llega un total manual, se distribuye proporcionalmente.
            $remainingTotal = (float) $totalAmount;
            $lastIndex = $preparedItems->count() - 1;
            foreach ($preparedItems->values() as $index => $prepared) {
                $item = $prepared['item'];
                $quantity = $prepared['itemData']['quantity'];
                $lineCatalogTotal = (float) $item->price * $quantity;
                $lineTotal = $index === $lastIndex
                    ? $remainingTotal
                    : round($catalogTotal > 0 ? ($lineCatalogTotal / $catalogTotal) * $totalAmount : 0, 2);
                $remainingTotal -= $lineTotal;

                $sale->items()->create([
                    'item_id' => $item->id,
                    'item_type' => $prepared['modelClass'],
                    'item_name' => $item->name,
                    'unit_price' => $quantity > 0 ? $lineTotal / $quantity : 0,
                    'quantity' => $quantity,
                    'total_price' => $lineTotal,
                ]);

                if ($prepared['modelClass'] === Product::class) {
                    $item->decrement('stock', $quantity);
                }
            }

            return $sale;
        });

        $sale->load('items', 'client', 'rider');
        $this->notifyRider($sale);

        return response()->json($sale, 201);
    }

    /**
     * Editar una orden rápida existente (Motorizado, Monto, Cantidad, Notas, etc.)
     */
    public function updateQuickOrder(Request $request, Sale $sale)
    {
        if ($sale->status !== 'pending') {
            return response()->json(['message' => 'Solo se pueden editar pedidos en estado pendiente.'], 422);
        }

        $validated = $request->validate([
            'rider_id' => 'sometimes|required|exists:users,id',
            'total_amount' => 'sometimes|required|numeric|min:0',
            'quantity' => 'sometimes|required|integer|min:1',
            'notes' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $shouldNotifyRider = array_key_exists('rider_id', $validated)
            && (int) $sale->rider_id !== (int) $validated['rider_id'];

        $sale = DB::transaction(function () use ($validated, $sale) {
            $item = $sale->items()->first();

            // 1. Manejar cambio de cantidad y stock
            if (isset($validated['quantity']) && $item && (int)$item->quantity !== (int)$validated['quantity']) {
                if ($item->item_type === Product::class) {
                    $product = Product::findOrFail($item->item_id);
                    $diff = $validated['quantity'] - $item->quantity;

                    if ($diff > 0 && $product->stock < $diff) {
                        throw ValidationException::withMessages([
                            'quantity' => ['Stock insuficiente para ' . $product->name . ' (Disponible: ' . $product->stock . ')']
                        ]);
                    }

                    $product->decrement('stock', $diff);
                }
                $item->quantity = $validated['quantity'];
            }

            // 2. Manejar cambio de monto total y recalcular unit_price
            // Si cambió el monto O la cantidad, recalculamos el precio unitario
            if ($item && (isset($validated['total_amount']) || isset($validated['quantity']))) {
                $newTotalAmount = isset($validated['total_amount']) ? $validated['total_amount'] : $sale->total_amount;

                $unitPrice = $newTotalAmount / $item->quantity;
                $item->update([
                    'unit_price' => $unitPrice,
                    'total_price' => $newTotalAmount,
                    'quantity' => $item->quantity,
                ]);

                $sale->total_amount = $newTotalAmount;
            }

            if (isset($validated['rider_id'])) {
                $sale->rider_id = $validated['rider_id'];
            }

            if (isset($validated['notes'])) {
                $sale->delivery_notes = $validated['notes'];
            }

            if (isset($validated['scheduled_at'])) {
                $sale->scheduled_at = $validated['scheduled_at'];
                $sale->created_at = $validated['scheduled_at']; // Actualizar fecha de creación para sincronización
            }

            $sale->save();
            return $sale;
        });

        $sale->load('items', 'client', 'rider');
        if ($shouldNotifyRider) {
            $this->notifyRider($sale);
        }

        return response()->json($sale);
    }

    /**
     * Revertir a estado pendiente para permitir edición completa.
     */
    public function reopen(Sale $sale)
    {
        if ($sale->status === 'cancelled') {
            return response()->json(['message' => 'No se puede reabrir una venta cancelada. Primero restáurela.'], 422);
        }

        DB::transaction(function () use ($sale) {
            // Al reabrir, el stock continúa reservado por la venta pendiente.
            // Solo se revierten caja, ganancia, pagos, crédito y descuentos.
            $this->revertSaleImpacts($sale, restoreStock: false);
            $sale->update(['status' => 'pending']);
        });

        return response()->json([
            'message' => 'Venta revertida a pendiente. Ahora puede editar los pagos o ítems.',
            'sale' => $sale->load('items', 'payments')
        ]);
    }

    /**
     * Cancelar una venta/pedido y devolver el stock.
     */
    public function cancel(Sale $sale)
    {
        if ($sale->status === 'cancelled') {
            return response()->json(['message' => 'La venta ya estaba cancelada.'], 422);
        }

        DB::transaction(function () use ($sale) {
            $this->revertSaleImpacts($sale);
            // Cambiar estado a cancelado
            $sale->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'Venta cancelada exitosamente y stock devuelto.']);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Sale::query()->with(['items', 'client', 'creator', 'rider', 'business', 'payments']);

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        // Filtro de Seguridad: Solo ver lo que creé o lo que me asignaron como rider
        $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('rider_id', $user->id);
        });


        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('sale_number', 'like', "%{$searchTerm}%")
                    ->orWhere('customer_name', 'like', "%{$searchTerm}%")
                    ->orWhereHas('rider', function ($riderQuery) use ($searchTerm) {
                        $riderQuery->where('first_name', 'like', "%{$searchTerm}%")
                            ->orWhere('last_name', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('creator', function ($creatorQuery) use ($searchTerm) {
                        $creatorQuery->where('first_name', 'like', "%{$searchTerm}%")
                            ->orWhere('last_name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        if ($request->filled('rider_id')) {
            $query->where('rider_id', $request->rider_id);
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $date = $request->date;
            $query->whereDate('created_at', $date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Por defecto no mostrar canceladas en el listado general
            $query->where('status', '!=', 'cancelled');
        }

        if ($request->filled('type')) {
            if ($request->type === 'delivery') {
                $query->where('is_delivery', true);
            } elseif ($request->type === 'pos') {
                $query->where('is_delivery', false);
            }
        }

        if ($request->filled('payment_method')) {
            $query->whereHas('payments', function ($q) use ($request) {
                $q->where('payment_method', $request->payment_method);
            });
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('rider_id')) {
            $query->where('rider_id', $request->rider_id);
        }

        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', $request->cash_register_id);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $sales = $query->latest('id')->paginate($perPage);

        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $business = Auth::user()->business;
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'client_id' => 'nullable|exists:clients,id',
            'rider_id' => 'nullable|exists:users,id',
            'delivery_address' => 'nullable|string|max:500',
            'delivery_phone' => 'nullable|string|max:20',
            'delivery_notes' => 'nullable|string',
            'is_delivery' => 'nullable|boolean',
            'scheduled_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.type' => 'required|string|in:product,service',
            'items.*.quantity' => 'required|integer|min:1',
            'payments' => 'nullable|array',
            'payments.*.payment_method' => 'required|string|in:cash,credit,yape,plin,card,transfer,discount,vale',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:255',
            'payments.*.payment_image' => $this->paymentImageRules(),
        ]);

        $isDelivery = $request->boolean('is_delivery');

        $sale = DB::transaction(function () use ($validated, $business, $isDelivery, $request) {
            // Determinar a qué caja va la venta
            $targetUserId = ($isDelivery && !empty($validated['rider_id']))
                ? $validated['rider_id']
                : Auth::id();

            $cashRegister = $business->cashRegisters()
                ->where('status', 'open')
                ->where('opened_by', $targetUserId)
                ->first();

            if (!$cashRegister) {
                $errorMessage = ($targetUserId === Auth::id())
                    ? 'No tienes una caja registradora abierta.'
                    : 'El motorizado asignado no tiene una caja abierta.';

                throw ValidationException::withMessages([
                    'cash_register' => [$errorMessage]
                ]);
            }

            $totalAmount = 0;
            foreach ($validated['items'] as $itemData) {
                $modelClass = $itemData['type'] === 'product' ? Product::class : Service::class;
                $item = $modelClass::findOrFail($itemData['id']);
                $totalAmount += $item->price * $itemData['quantity'];
            }
            $catalogTotal = $totalAmount;

            $payments = $validated['payments'] ?? [];
            $totalPaid = collect($payments)->sum('amount');

            // Cuando la venta ya trae pagos, su suma representa el precio final acordado.
            // Esto permite aplicar un precio manual, igual que en los pedidos rápidos.
            if (count($payments) > 0) {
                $totalAmount = $totalPaid;
            }

            $hasCredit = collect($payments)->contains('payment_method', 'credit');

            $scheduledAt = $validated['scheduled_at'] ?? now();
            $sale = $business->sales()->create([
                'customer_name' => $validated['customer_name'],
                'client_id' => $validated['client_id'] ?? null,
                'rider_id' => $validated['rider_id'] ?? null,
                'delivery_address' => $validated['delivery_address'] ?? null,
                'delivery_phone' => $validated['delivery_phone'] ?? null,
                'delivery_notes' => $validated['delivery_notes'] ?? null,
                'is_delivery' => $isDelivery,
                'scheduled_at' => $scheduledAt,
                'created_at' => $scheduledAt, // Usar fecha programada como creación para sincronización
                'created_by' => Auth::id(),
                'cash_register_id' => $cashRegister ? $cashRegister->id : null,
                'total_amount' => $totalAmount,
                'status' => ($isDelivery && count($payments) === 0) ? 'pending' : ($hasCredit ? 'debt' : 'completed'),
            ]);

            $totalProfit = 0;
            $remainingTotal = (float) $totalAmount;
            $lastItemIndex = count($validated['items']) - 1;
            foreach ($validated['items'] as $index => $itemData) {
                $modelClass = $itemData['type'] === 'product' ? Product::class : Service::class;
                $item = $modelClass::findOrFail($itemData['id']);
                $catalogLineTotal = (float) $item->price * $itemData['quantity'];
                $lineTotal = $index === $lastItemIndex
                    ? $remainingTotal
                    : round($catalogTotal > 0
                        ? ($catalogLineTotal / $catalogTotal) * $totalAmount
                        : 0, 2);
                $remainingTotal -= $lineTotal;
                $unitPrice = $lineTotal / $itemData['quantity'];

                if ($itemData['type'] === 'product') {
                    if ($item->stock < $itemData['quantity']) {
                        throw new \Exception('Stock insuficiente para el producto: ' . $item->name);
                    }
                    $item->decrement('stock', $itemData['quantity']);

                    // Calcular ganancia del producto: (Precio Venta - Costo) * Cantidad
                    $totalProfit += ($unitPrice - $item->cost) * $itemData['quantity'];
                } else {
                    // Para servicios, la ganancia es el precio total (asumiendo costo 0 o no definido)
                    $totalProfit += $lineTotal;
                }

                $sale->items()->create([
                    'item_id' => $item->id,
                    'item_type' => $modelClass,
                    'item_name' => $item->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $itemData['quantity'],
                    'total_price' => $lineTotal,
                ]);
            }

            // Solo afectar caja si ya está completada o no es delivery fantasma
            if ($sale->status === 'completed' && $cashRegister) {
                $cashRegister->increment('expected_amount', $totalAmount);
                $cashRegister->increment('profit', $totalProfit);

                foreach ($payments as $index => $payment) {
                    $sale->payments()->create([
                        'payment_method' => $payment['payment_method'],
                        'amount' => $payment['amount'],
                        'reference' => $payment['reference'] ?? null,
                        'payment_image' => $this->storePaymentImage($request, $index),
                    ]);

                    if ($payment['payment_method'] === 'cash') {
                        $cashRegister->increment('cash_sales_amount', $payment['amount']);
                    }
                    // ... (resto del código de descuentos)

                    if ($payment['payment_method'] === 'discount') {
                        // Registrar como Gasto automático
                        $category = Category::firstOrCreate(
                            ['name' => 'Descuentos en Ventas', 'business_id' => $business->id],
                            ['description' => 'Descuentos aplicados en ventas delivery o POS']
                        );

                        $expense = new Expense([
                            'description' => "Descuento en venta POS {$sale->sale_number}",
                            'amount' => $payment['amount'],
                            'expense_date' => $sale->created_at,
                            'category_id' => $category->id,
                            'business_id' => $business->id,
                            'created_by' => $targetUserId,
                            'notes' => "Aplicado automáticamente en venta POS #{$sale->id}"
                        ]);
                        $expense->created_at = $sale->created_at;
                        $expense->updated_at = $sale->created_at;
                        $expense->save();
                    }
                }
            } elseif ($hasCredit && $cashRegister) {
                // Si es crédito pero POS directo, registramos el crédito pero aún no el efectivo
                $cashRegister->increment('expected_amount', $totalAmount);
                $cashRegister->increment('profit', $totalProfit);
                foreach ($payments as $index => $payment) {
                    $sale->payments()->create([
                        'payment_method' => $payment['payment_method'],
                        'amount' => $payment['amount'],
                        'reference' => $payment['reference'] ?? null,
                        'payment_image' => $this->storePaymentImage($request, $index),
                    ]);
                    if ($payment['payment_method'] === 'cash') {
                        $cashRegister->increment('cash_sales_amount', $payment['amount']);
                    }
                    if ($payment['payment_method'] === 'discount') {
                        // Registrar como Gasto automático
                        $category = Category::firstOrCreate(
                            ['name' => 'Descuentos en Ventas', 'business_id' => $business->id],
                            ['description' => 'Descuentos aplicados en ventas delivery o POS']
                        );

                        $expense = new Expense([
                            'description' => "Descuento en venta Mixta {$sale->sale_number}",
                            'amount' => $payment['amount'],
                            'expense_date' => $sale->created_at,
                            'category_id' => $category->id,
                            'business_id' => $business->id,
                            'created_by' => $targetUserId,
                            'notes' => "Aplicado automáticamente en venta mixta #{$sale->id}"
                        ]);
                        $expense->created_at = $sale->created_at;
                        $expense->updated_at = $sale->created_at;
                        $expense->save();
                    }
                }
            }

            // Manejo de créditos si es el caso
            if ($hasCredit) {
                foreach ($payments as $payment) {
                    if ($payment['payment_method'] === 'credit') {
                        $business->credits()->create([
                            'sale_id' => $sale->id,
                            'customer_name' => $sale->customer_name,
                            'total_amount' => $payment['amount'],
                            'pending_amount' => $payment['amount'],
                            'due_date' => now()->addDays(30),
                            'created_by' => Auth::id(),
                        ]);
                    }
                }
            }

            return $sale;
        });

        $sale->load('items', 'payments', 'client', 'rider');
        if ($sale->is_delivery && $sale->rider) {
            $this->notifyRider($sale);
        }

        return response()->json($sale, 201);
    }

    public function confirmDelivery(Request $request, Sale $sale)
    {
        if ($sale->status !== 'pending' || !$sale->is_delivery) {
            return response()->json(['message' => 'Esta venta no es un delivery pendiente o ya ha sido procesada.'], 422);
        }

        $validated = $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|string|in:cash,credit,yape,plin,card,transfer,discount,vale', // Se añade 'vale'
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:255',
            'payments.*.payment_image' => $this->paymentImageRules(),
        ]);

        $business = Auth::user()->business;

        $sale = DB::transaction(function () use ($validated, $sale, $business, $request) {
            $targetUserId = $sale->rider_id ?? Auth::id();

            $cashRegister = $business->cashRegisters()
                ->where('status', 'open')
                ->where('opened_by', $targetUserId)
                ->first();

            if (!$cashRegister) {
                $errorMessage = ($targetUserId === Auth::id())
                    ? 'No tienes una caja registradora abierta para confirmar esta entrega.'
                    : 'El repartidor asignado no tiene una caja abierta para confirmar esta entrega.';

                throw ValidationException::withMessages([
                    'cash_register' => [$errorMessage]
                ]);
            }

            $totalPaid = collect($validated['payments'])->sum('amount');

            // El pago informado al entregar es el precio final de la venta.
            $catalogTotal = (float) $sale->items->sum('total_price');
            $remainingTotal = (float) $totalPaid;
            $lastItemIndex = $sale->items->count() - 1;
            foreach ($sale->items->values() as $index => $saleItem) {
                $lineTotal = $index === $lastItemIndex
                    ? $remainingTotal
                    : round($catalogTotal > 0
                        ? ((float) $saleItem->total_price / $catalogTotal) * $totalPaid
                        : 0, 2);
                $remainingTotal -= $lineTotal;
                $saleItem->update([
                    'unit_price' => $lineTotal / $saleItem->quantity,
                    'total_price' => $lineTotal,
                ]);
            }

            $hasCredit = collect($validated['payments'])->contains('payment_method', 'credit');

            $sale->update([
                'total_amount' => $totalPaid,
                'status' => $hasCredit ? 'debt' : 'completed',
                'cash_register_id' => $cashRegister->id,
            ]);

            // No incrementamos la caja con el monto total si hay descuentos que no queremos contar como "dinero esperado"
            // Pero para mantener la consistencia, incrementamos y luego restamos o simplemente sumamos lo no-descuento.
            // Decisión: Sumar todo al expected_amount para que el arqueo cuadre con la venta, 
            // pero registrar el descuento como gasto.
            $cashRegister->increment('expected_amount', $sale->total_amount);

            // Calcular ganancia del delivery
            $saleProfit = 0;
            foreach ($sale->items as $saleItem) {
                $item = $saleItem->item;
                if ($saleItem->item_type === Product::class && $item) {
                    $saleProfit += ($saleItem->unit_price - $item->cost) * $saleItem->quantity;
                } elseif ($saleItem->item_type === Service::class) {
                    $saleProfit += $saleItem->total_price;
                }
            }
            $cashRegister->increment('profit', $saleProfit);

            foreach ($validated['payments'] as $index => $payment) {
                $sale->payments()->create([
                    'amount' => $payment['amount'],
                    'payment_method' => $payment['payment_method'],
                    'reference' => $payment['reference'] ?? null,
                    'payment_image' => $this->storePaymentImage($request, $index),
                ]);

                if ($payment['payment_method'] === 'cash') {
                    $cashRegister->increment('cash_sales_amount', $payment['amount']);
                }

                if ($payment['payment_method'] === 'discount') {
                    // Registrar como Gasto automático (solo para descuentos reales)
                    $category = Category::firstOrCreate(
                        ['name' => 'Descuentos en Ventas', 'business_id' => $business->id],
                        ['description' => 'Descuentos aplicados en ventas']
                    );

                    $expense = new Expense([
                        'description' => "Descuento en venta {$sale->sale_number}",
                        'amount' => $payment['amount'],
                        'expense_date' => $sale->created_at,
                        'category_id' => $category->id,
                        'business_id' => $business->id,
                        'created_by' => $targetUserId,
                        'notes' => "Aplicado automáticamente al confirmar entrega de venta #{$sale->id}"
                    ]);
                    $expense->created_at = $sale->created_at;
                    $expense->updated_at = $sale->created_at;
                    $expense->save();
                }
                if ($payment['payment_method'] === 'credit') {
                    $business->credits()->create([
                        'sale_id' => $sale->id,
                        'customer_name' => $sale->customer_name,
                        'total_amount' => $payment['amount'],
                        'pending_amount' => $payment['amount'],
                        'due_date' => now()->addDays(30),
                        'created_by' => Auth::id(),
                    ]);
                }
            }

            return $sale;
        });

        return response()->json($sale->load('items', 'payments', 'client', 'rider'));
    }

    public function show(Sale $sale)
    {
        return $sale->load('items.item', 'creator', 'cashRegister');
    }

    private function storePaymentImage(Request $request, int $index): ?string
    {
        if ($request->hasFile("payments.{$index}.payment_image")) {
            $file = $request->file("payments.{$index}.payment_image");
            $imagePath = 'payments/' . uniqid() . '.jpg';

            try {
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $image = $manager->read($file);
                $image->scaleDown(width: 1200);
                Storage::disk('public')->put($imagePath, (string) $image->toJpeg(75));

                return $imagePath;
            } catch (\Exception $e) {
                return $file->store('payments', 'public');
            }
        }

        return $this->storeBase64PaymentImage(
            $request->input("payments.{$index}.payment_image")
        );
    }

    private function paymentImageRules(): array
    {
        return ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
            $maxBytes = 10 * 1024 * 1024;
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                if (!$value->isValid() || @getimagesize($value->getRealPath()) === false) {
                    $fail("El campo {$attribute} debe ser una imagen válida.");
                } elseif ($value->getSize() > $maxBytes) {
                    $fail("El campo {$attribute} no debe superar los 10 MB.");
                }
                return;
            }

            if (is_string($value) && preg_match('/^data:image\/(jpeg|jpg|png|webp|gif|bmp);base64,(.+)$/s', $value, $matches)) {
                $decoded = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
                if ($decoded === false || @getimagesizefromstring($decoded) === false) {
                    $fail("El campo {$attribute} contiene una imagen Base64 inválida.");
                } elseif (strlen($decoded) > $maxBytes) {
                    $fail("El campo {$attribute} no debe superar los 10 MB.");
                }
                return;
            }

            $fail("El campo {$attribute} debe enviarse como archivo de imagen o Data URL Base64.");
        }];
    }

    private function storeBase64PaymentImage(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^data:image\/(jpeg|jpg|png|webp|gif|bmp);base64,(.+)$/s', $value, $matches)) {
            return null;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $imagePath = 'payments/' . uniqid() . '.' . $extension;
        Storage::disk('public')->put(
            $imagePath,
            base64_decode(preg_replace('/\s+/', '', $matches[2]), true)
        );

        return $imagePath;
    }

    public function destroy(Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            // Una venta cancelada ya devolvió su stock y revirtió sus impactos.
            // Evitamos ejecutar la reversión una segunda vez al eliminarla.
            if ($sale->status !== 'cancelled') {
                $this->revertSaleImpacts($sale);
            }

            // Eliminar los items (Soft delete)
            $sale->items()->delete();

            // Eliminar la venta (Soft delete)
            $sale->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Lógica centralizada para revertir todos los efectos financieros y de stock de una venta.
     * Protege la integridad de la caja, el inventario, las deudas y los gastos.
     */
    protected function revertSaleImpacts(Sale $sale, bool $restoreStock = true)
    {
        // 1. Revertir montos de caja registradora
        // Solo si la venta afectó la caja (estados completed o debt)
        if (in_array($sale->status, ['completed', 'debt']) && $sale->cash_register_id) {
            $cashRegister = $sale->cashRegister;
            if ($cashRegister) {
                $cashRegister->decrement('expected_amount', $sale->total_amount);

                // Calcular la ganancia que se debe restar
                $saleProfit = 0;
                foreach ($sale->items as $saleItem) {
                    $item = $saleItem->item; // Cargado vía MorphTo
                    if ($saleItem->item_type === Product::class && $item) {
                        $saleProfit += ($saleItem->unit_price - $item->cost) * $saleItem->quantity;
                    } elseif ($saleItem->item_type === Service::class) {
                        $saleProfit += $saleItem->total_price;
                    }
                }
                $cashRegister->decrement('profit', $saleProfit);

                foreach ($sale->payments as $payment) {
                    if ($payment->payment_method === 'cash') {
                        // Distinguir entre venta directa y cobranza de crédito mediante el prefijo en reference
                        if ($payment->reference && str_starts_with($payment->reference, 'Cobranza:')) {
                            $cashRegister->decrement('credit_collections', $payment->amount);
                        } else {
                            $cashRegister->decrement('cash_sales_amount', $payment->amount);
                        }
                    }
                }
            }
        }

        // 2. Devolver stock únicamente al cancelar o eliminar una venta activa.
        // Al reabrir, el inventario debe permanecer reservado.
        if ($restoreStock) {
            foreach ($sale->items as $item) {
                if ($item->item_type === Product::class) {
                    $product = Product::find($item->item_id);
                    if ($product) {
                        $product->increment('stock', $item->quantity);
                    }
                }
            }
        }

        // 3. Eliminar Crédito asociado (si existe)
        if ($sale->credit) {
            $sale->credit->delete(); // Soft Delete
        }

        // 4. Eliminar Gastos por descuentos automáticos
        // Buscamos gastos que tengan el ID de la venta en las notas para ser precisos
        Expense::where('business_id', $sale->business_id)
            ->where('notes', 'like', "%#{$sale->id}")
            ->where('description', 'like', "%Descuento%")
            ->delete(); // Soft Delete

        // 5. Eliminar registros de pagos (Soft Delete)
        $sale->payments()->delete();
    }

    public function getDailySales(Request $request)
    {
        $user = Auth::user();
        $date = $request->query('date', Carbon::now()->format('Y-m-d'));

        $query = Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', $date)
            ->with('items', 'creator', 'rider');

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        // Filtro de Seguridad
        $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('rider_id', $user->id);
        });


        $sales = $query->latest('id')->get();

        return response()->json($sales);
    }

    public function getMonthlySales(Request $request, $year, $month)
    {
        $user = Auth::user();

        $query = Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->with('items', 'creator', 'rider');

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        // Filtro de Seguridad
        $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('rider_id', $user->id);
        });


        $perPage = $this->getPaginationSize($request, $query);
        $sales = $query->latest('id')->paginate($perPage);

        return response()->json($sales);
    }

    public function generateReceipt(Sale $sale)
    {
        if (Auth::user()->business_id !== $sale->business_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $sale->load('items', 'business', 'creator');

        $business = $sale->business;

        $pdf = Pdf::loadView('pdf.sale_receipt', compact('sale', 'business'))
            ->setPaper([0, 0, 227, 650]);

        return $pdf->stream('receipt-' . $sale->sale_number . '.pdf');
    }

    public function showPublicReceipt($uuid)
    {
        $sale = Sale::where('uuid', $uuid)->firstOrFail();

        $sale->load('items', 'business', 'creator');

        $business = $sale->business;

        $pdf = Pdf::loadView('pdf.sale_receipt', compact('sale', 'business'))
            ->setPaper([0, 0, 227, 650]);

        return $pdf->stream('receipt-' . $sale->sale_number . '.pdf');
    }

    /**
     * Obtener la línea de tiempo (auditoría) de una venta.
     */
    public function timeline($id)
    {
        $sale = Sale::withTrashed()->findOrFail($id);
        return response()->json($sale->getDeepTimeline());
    }

    private function notifyRider(Sale $sale): void
    {
        if (! $sale->rider) {
            return;
        }

        try {
            $sale->rider->notify(new OrderAssignedNotification($sale));
        } catch (\Throwable $exception) {
            Log::error('El pedido fue guardado, pero no se pudo crear su notificación.', [
                'sale_id' => $sale->id,
                'user_id' => $sale->rider_id,
                'exception' => $exception,
            ]);
        }
    }
}

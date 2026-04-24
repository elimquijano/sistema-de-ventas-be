<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Service;
use App\Models\CashRegister;
use App\Models\SalePayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Models\Client;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\WhatsAppService;

class SaleController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

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
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'total_amount' => 'required|numeric|min:0',
            'rider_id' => 'required|exists:users,id',
            'notes' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $sale = DB::transaction(function () use ($validated, $business) {
            // 1. Buscar o Crear Cliente (Basado en teléfono Y dirección para permitir múltiples ubicaciones)
            $client = Client::where('phone', $validated['phone'])
                ->where('address', $validated['address'])
                ->where('business_id', $business->id)
                ->first();

            if (!$client) {
                $client = Client::create([
                    'phone' => $validated['phone'],
                    'name' => $validated['customer_name'] ?? 'Cliente (' . $validated['phone'] . ')',
                    'address' => $validated['address'],
                    'address_detail' => $validated['notes'] ?? null,
                    'latitude' => $validated['latitude'] ?? 0,
                    'longitude' => $validated['longitude'] ?? 0,
                    'business_id' => $business->id,
                    'created_by_user_id' => Auth::id(),
                ]);
            }

            // 2. Preparar el Producto
            $product = Product::findOrFail($validated['product_id']);
            if ($product->stock < $validated['quantity']) {
                throw ValidationException::withMessages([
                    'product_id' => ['Stock insuficiente para ' . $product->name . ' (Disponible: ' . $product->stock . ')']
                ]);
            }

            // 3. Crear la Venta (Borrador/Pending)
            $scheduledAt = $validated['scheduled_at'] ?? now();
            $sale = $business->sales()->create([
                'customer_name' => $client->name,
                'client_id' => $client->id,
                'rider_id' => $validated['rider_id'],
                'delivery_address' => $validated['address'] ?? $client->address,
                'delivery_phone' => $client->phone,
                'delivery_notes' => $validated['notes'],
                'is_delivery' => true,
                'scheduled_at' => $scheduledAt,
                'created_at' => $scheduledAt, // Usar la fecha programada como fecha de creación para sincronización
                'created_by' => Auth::id(),
                'total_amount' => $validated['total_amount'], // Monto manual
                'status' => 'pending',
            ]);

            // 4. Crear el Item (con precio ajustado para que cuadre el total)
            $unitPrice = $validated['total_amount'] / $validated['quantity'];
            $sale->items()->create([
                'item_id' => $product->id,
                'item_type' => Product::class,
                'item_name' => $product->name,
                'unit_price' => $unitPrice,
                'quantity' => $validated['quantity'],
                'total_price' => $validated['total_amount'],
            ]);

            // 5. Reservar Stock
            $product->decrement('stock', $validated['quantity']);

            // 6. Preparar Mensaje para el Repartidor (WhatsApp)
            // Solo enviar si el pedido no es de hace más de 15 minutos (para evitar duplicados en sincronización)
            $scheduledAt = Carbon::parse($sale->scheduled_at);
            if ($scheduledAt->isAfter(now()->subMinutes(15))) {
                $whatsappMsg = $this->whatsappService->formatSaleMessage($sale);

                // Buscar teléfono del rider
                $rider = User::find($validated['rider_id']);
                if ($rider && $rider->phone) {
                    $this->whatsappService->sendMessage($rider->phone, $whatsappMsg);
                }
            }

            return $sale;
        });

        return response()->json($sale->load('items', 'client', 'rider'), 201);
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
                // Opcional: Re-enviar WhatsApp al nuevo rider si ha cambiado
                if ($sale->isDirty('rider_id')) {
                    $rider = User::find($validated['rider_id']);

                    // Solo enviar si el pedido no es de hace más de 15 minutos (para evitar duplicados en sincronización)
                    $scheduledAtValue = $validated['scheduled_at'] ?? $sale->scheduled_at;
                    $scheduledAt = Carbon::parse($scheduledAtValue);

                    if ($scheduledAt->isAfter(now()->subMinutes(15)) && $rider && $rider->phone) {
                        $whatsappMsg = $this->whatsappService->formatSaleMessage($sale);
                        $this->whatsappService->sendMessage($rider->phone, $whatsappMsg);
                    }
                }
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

        return response()->json($sale->load('items', 'client', 'rider'));
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
            $this->revertSaleImpacts($sale);
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
        // A menos que sea administrador del negocio (suponiendo rol 'admin' o 'owner')
        if (!$user->hasRole(['admin', 'owner'])) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('rider_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('sale_number', 'like', "%{$searchTerm}%")
                    ->orWhere('customer_name', 'like', "%{$searchTerm}%");
            });
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
            'payments.*.payment_method' => 'required|string|in:cash,credit,yape,plin,card,transfer,discount',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:255',
        ]);

        $isDelivery = $request->boolean('is_delivery');

        $sale = DB::transaction(function () use ($validated, $business, $isDelivery) {
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

            $payments = $validated['payments'] ?? [];
            $totalPaid = collect($payments)->sum('amount');

            // Solo validar el total si no es delivery o si ya trae pagos
            if (!$isDelivery || count($payments) > 0) {
                if (bccomp($totalAmount, $totalPaid, 2) !== 0) {
                    throw ValidationException::withMessages([
                        'payments' => 'La suma de los pagos (' . $totalPaid . ') no coincide con el monto total de la venta (' . $totalAmount . ').'
                    ]);
                }
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

            foreach ($validated['items'] as $itemData) {
                $modelClass = $itemData['type'] === 'product' ? Product::class : Service::class;
                $item = $modelClass::findOrFail($itemData['id']);

                if ($itemData['type'] === 'product') {
                    if ($item->stock < $itemData['quantity']) {
                        throw new \Exception('Stock insuficiente para el producto: ' . $item->name);
                    }
                    $item->decrement('stock', $itemData['quantity']);
                }

                $sale->items()->create([
                    'item_id' => $item->id,
                    'item_type' => $modelClass,
                    'item_name' => $item->name,
                    'unit_price' => $item->price,
                    'quantity' => $itemData['quantity'],
                    'total_price' => $item->price * $itemData['quantity'],
                ]);
            }

            // Solo afectar caja si ya está completada o no es delivery fantasma
            if ($sale->status === 'completed' && $cashRegister) {
                $cashRegister->increment('expected_amount', $totalAmount);

                foreach ($payments as $payment) {
                    $sale->payments()->create($payment);

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
                foreach ($payments as $payment) {
                    $sale->payments()->create($payment);
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
                        ]);
                    }
                }
            }

            return $sale;
        });

        return response()->json($sale->load('items', 'payments', 'client', 'rider'), 201);
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
            'payments.*.payment_image' => 'nullable|image',// |max:2048', // Se añade imagen
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

            if (bccomp($sale->total_amount, $totalPaid, 2) !== 0) {
                throw ValidationException::withMessages([
                    'payments' => 'La suma de los pagos (' . $totalPaid . ') no coincide con el monto total de la venta (' . $sale->total_amount . ').'
                ]);
            }

            $hasCredit = collect($validated['payments'])->contains('payment_method', 'credit');

            $sale->update([
                'status' => $hasCredit ? 'debt' : 'completed',
                'cash_register_id' => $cashRegister->id,
            ]);

            // No incrementamos la caja con el monto total si hay descuentos que no queremos contar como "dinero esperado"
            // Pero para mantener la consistencia, incrementamos y luego restamos o simplemente sumamos lo no-descuento.
            // Decisión: Sumar todo al expected_amount para que el arqueo cuadre con la venta, 
            // pero registrar el descuento como gasto.
            $cashRegister->increment('expected_amount', $sale->total_amount);

            foreach ($validated['payments'] as $index => $payment) {
                $imagePath = null;
                if ($request->hasFile("payments.{$index}.payment_image")) {
                    $file = $request->file("payments.{$index}.payment_image");
                    $filename = uniqid() . '.jpg';
                    $imagePath = "payments/{$filename}";

                    try {
                        // Usar Intervention Image v3 para comprimir
                        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                        $image = $manager->read($file);
                        
                        // Redimensionar si es muy grande (max 1200px) manteniendo aspecto
                        $image->scale(width: 1200);
                        
                        // Codificar como JPG con calidad 75% para ahorrar espacio
                        $encoded = $image->toJpeg(75);
                        
                        // Guardar en el disco public
                        Storage::disk('public')->put($imagePath, (string) $encoded);
                    } catch (\Exception $e) {
                        // Si falla el procesamiento, guardar el original como respaldo
                        $imagePath = $file->store('payments', 'public');
                    }
                }

                $sale->payments()->create([
                    'amount' => $payment['amount'],
                    'payment_method' => $payment['payment_method'],
                    'reference' => $payment['reference'],
                    'payment_image' => $imagePath,
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

    public function destroy(Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            $this->revertSaleImpacts($sale);

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
    protected function revertSaleImpacts(Sale $sale)
    {
        // 1. Revertir montos de caja registradora
        // Solo si la venta afectó la caja (estados completed o debt)
        if (in_array($sale->status, ['completed', 'debt']) && $sale->cash_register_id) {
            $cashRegister = $sale->cashRegister;
            if ($cashRegister) {
                $cashRegister->decrement('expected_amount', $sale->total_amount);

                foreach ($sale->payments as $payment) {
                    if ($payment->payment_method === 'cash') {
                        $cashRegister->decrement('cash_sales_amount', $payment->amount);
                    }
                }
            }
        }

        // 2. Revertir Stock de productos
        foreach ($sale->items as $item) {
            if ($item->item_type === Product::class) {
                // Asegurarse de tener el modelo del producto para usar increment
                $product = Product::find($item->item_id);
                if ($product) {
                    $product->increment('stock', $item->quantity);
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
        if (!$user->hasRole(['admin', 'owner'])) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('rider_id', $user->id);
            });
        }

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
        if (!$user->hasRole(['admin', 'owner'])) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('rider_id', $user->id);
            });
        }

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
}

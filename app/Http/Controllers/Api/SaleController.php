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
            // 1. Buscar o Crear Cliente
            $client = Client::where('phone', $validated['phone'])
                ->where('business_id', $business->id)
                ->first();

            if (!$client) {
                $client = Client::create([
                    'phone' => $validated['phone'],
                    'name' => $validated['customer_name'] ?? 'Cliente Nuevo (' . $validated['phone'] . ')',
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
            $sale = $business->sales()->create([
                'customer_name' => $client->name,
                'client_id' => $client->id,
                'rider_id' => $validated['rider_id'],
                'delivery_address' => $validated['address'] ?? $client->address,
                'delivery_phone' => $client->phone,
                'delivery_notes' => $validated['notes'],
                'is_delivery' => true,
                'scheduled_at' => $validated['scheduled_at'] ?? now(),
                'created_by' => $validated['rider_id'],
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
            $whatsappMsg = $this->whatsappService->formatSaleMessage($sale);

            // Buscar teléfono del rider
            $rider = User::find($validated['rider_id']);
            if ($rider && $rider->phone) {
                $this->whatsappService->sendMessage($rider->phone, $whatsappMsg);
            }

            return $sale;
        });

        return response()->json($sale->load('items', 'client', 'rider'), 201);
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
            // Revertir montos de caja si ya estaba completada
            if ($sale->status === 'completed' && $sale->cashRegister) {
                $sale->cashRegister->decrement('expected_amount', $sale->total_amount);

                foreach ($sale->payments as $payment) {
                    if ($payment->payment_method === 'cash') {
                        $sale->cashRegister->decrement('cash_sales_amount', $payment->amount);
                    }
                }
            }

            // Devolver stock
            foreach ($sale->items as $item) {
                if ($item->item_type === Product::class) {
                    $product = Product::find($item->item_id);
                    if ($product) {
                        $product->increment('stock', $item->quantity);
                    }
                }
            }

            // Cambiar estado a cancelado
            $sale->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'Venta cancelada exitosamente y stock devuelto.']);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Sale::query()->with(['items', 'client', 'creator', 'business', 'payments']);

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
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

        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', $request->cash_register_id);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $sales = $query->latest()->paginate($perPage);

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

            $sale = $business->sales()->create([
                'customer_name' => $validated['customer_name'],
                'client_id' => $validated['client_id'] ?? null,
                'rider_id' => $validated['rider_id'] ?? null,
                'delivery_address' => $validated['delivery_address'] ?? null,
                'delivery_phone' => $validated['delivery_phone'] ?? null,
                'delivery_notes' => $validated['delivery_notes'] ?? null,
                'is_delivery' => $isDelivery,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'created_by' => $targetUserId,
                'cash_register_id' => $cashRegister ? $cashRegister->id : null,
                'total_amount' => $totalAmount,
                'status' => ($isDelivery && count($payments) === 0) ? 'pending' : ($hasCredit ? 'pending' : 'completed'),
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

                        Expense::create([
                            'description' => "Descuento en venta POS {$sale->sale_number}",
                            'amount' => $payment['amount'],
                            'expense_date' => now(),
                            'category_id' => $category->id,
                            'business_id' => $business->id,
                            'created_by' => $targetUserId,
                            'notes' => "Aplicado automáticamente en venta POS #{$sale->id}"
                        ]);
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

                        Expense::create([
                            'description' => "Descuento en venta Mixta {$sale->sale_number}",
                            'amount' => $payment['amount'],
                            'expense_date' => now(),
                            'category_id' => $category->id,
                            'business_id' => $business->id,
                            'created_by' => $targetUserId,
                            'notes' => "Aplicado automáticamente en venta mixta #{$sale->id}"
                        ]);
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
            'payments.*.payment_method' => 'required|string|in:cash,credit,yape,plin,card,transfer,discount',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:255',
        ]);

        $business = Auth::user()->business;

        $sale = DB::transaction(function () use ($validated, $sale, $business) {
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
                'status' => $hasCredit ? 'pending' : 'completed',
                'cash_register_id' => $cashRegister->id,
            ]);

            // No incrementamos la caja con el monto total si hay descuentos que no queremos contar como "dinero esperado"
            // Pero para mantener la consistencia, incrementamos y luego restamos o simplemente sumamos lo no-descuento.
            // Decisión: Sumar todo al expected_amount para que el arqueo cuadre con la venta, 
            // pero registrar el descuento como gasto.
            $cashRegister->increment('expected_amount', $sale->total_amount);

            foreach ($validated['payments'] as $payment) {
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

                    Expense::create([
                        'description' => "Descuento en venta {$sale->sale_number}",
                        'amount' => $payment['amount'],
                        'expense_date' => now(),
                        'category_id' => $category->id,
                        'business_id' => $business->id,
                        'created_by' => $targetUserId,
                        'notes' => "Aplicado automáticamente al confirmar entrega de venta #{$sale->id}"
                    ]);
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
            // Revert cash register amounts
            if ($sale->cashRegister) {
                $sale->cashRegister->decrement('expected_amount', $sale->total_amount);

                foreach ($sale->payments as $payment) {
                    if ($payment->payment_method === 'cash') {
                        $sale->cashRegister->decrement('cash_sales_amount', $payment->amount);
                    }
                }
            }

            // Delete associated credit if exists
            if ($sale->credit) {
                $sale->credit->delete();
            }

            // Restore stock for products
            foreach ($sale->items as $item) {
                if ($item->item_type === Product::class) {
                    // The 'item' relationship might not be loaded, so load it if necessary
                    $product = $item->item ?? Product::find($item->item_id);
                    if ($product) {
                        $product->increment('stock', $item->quantity);
                    }
                }
            }

            // Delete payment records
            $sale->payments()->delete();

            // Delete sale items
            $sale->items()->delete();

            // Delete the sale
            $sale->delete();
        });

        return response()->json(null, 204);
    }

    public function getDailySales(Request $request)
    {
        $user = Auth::user();
        $date = $request->query('date', Carbon::now()->format('Y-m-d'));

        $query = Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', $date)
            ->with('items', 'creator');

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        $sales = $query->latest()->get();

        return response()->json($sales);
    }

    public function getMonthlySales(Request $request, $year, $month)
    {
        $user = Auth::user();

        $query = Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->with('items', 'creator');

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $sales = $query->latest()->paginate($perPage);

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
}

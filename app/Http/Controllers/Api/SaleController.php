<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Auth::user()->business->sales()->with(['items', 'creator', 'business']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('sale_number', 'like', "%{$searchTerm}%")
                    ->orWhere('customer_name', 'like', "%{$searchTerm}%");
            });
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $sales = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $business = Auth::user()->business;
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'payment_method' => 'required|in:cash,card,transfer,credit',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.type' => 'required|string|in:product,service',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $sale = DB::transaction(function () use ($validated, $business) {
            $cashRegister = $business->cashRegisters()->where('status', 'open')->firstOrFail();

            $sale = $business->sales()->create([
                'customer_name' => $validated['customer_name'],
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_method'] === 'credit' ? 'pending' : 'paid',
                'created_by' => Auth::id(),
                'cash_register_id' => $cashRegister->id,
                'total_amount' => 0,
            ]);

            $totalAmount = 0;
            foreach ($validated['items'] as $itemData) {
                $modelClass = $itemData['type'] === 'product' ? Product::class : Service::class;
                $item = $modelClass::findOrFail($itemData['id']);

                if ($itemData['type'] === 'product') {
                    if ($item->stock < $itemData['quantity']) {
                        throw new \Exception('Stock insuficiente para el producto: ' . $item->name);
                    }
                    $item->decrement('stock', $itemData['quantity']);
                }

                $totalPrice = $item->price * $itemData['quantity'];
                $totalAmount += $totalPrice;

                $sale->items()->create([
                    'item_id' => $item->id,
                    'item_type' => $modelClass,
                    'item_name' => $item->name,
                    'unit_price' => $item->price,
                    'quantity' => $itemData['quantity'],
                    'total_price' => $totalPrice,
                ]);
            }

            $sale->update(['total_amount' => $totalAmount]);

            // SIEMPRE INCREMENTAR expected_amount con el total de la venta
            $cashRegister->increment('expected_amount', $totalAmount);

            // Solo incrementar cash_sales_amount si el pago es en efectivo
            if ($sale->payment_method === 'cash') {
                $cashRegister->increment('cash_sales_amount', $totalAmount);
            }

            if ($sale->payment_method === 'credit') {
                $business->credits()->create([
                    'sale_id' => $sale->id,
                    'customer_name' => $sale->customer_name,
                    'total_amount' => $totalAmount,
                    'pending_amount' => $totalAmount,
                    'due_date' => now()->addDays(30),
                ]);
            }

            return $sale;
        });

        return response()->json($sale->load('items'), 201);
    }

    public function show(Sale $sale)
    {
        return $sale->load('items.item', 'creator', 'cashRegister');
    }

    public function destroy(Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            // Decrementar expected_amount al eliminar la venta
            if ($sale->cashRegister) {
                $sale->cashRegister->decrement('expected_amount', $sale->total_amount);
            }
            // Revertir el monto en la caja si se elimina una venta en efectivo
            if ($sale->payment_method === 'cash' && $sale->cashRegister) {
                $sale->cashRegister->decrement('cash_sales_amount', $sale->total_amount);
            }
            foreach ($sale->items as $item) {
                if ($item->item_type === Product::class) {
                    $item->item->increment('stock', $item->quantity);
                }
            }
            $sale->delete();
        });
        return response()->json(null, 204);
    }

    public function getDailySales(Request $request)
    {
        $date = $request->query('date', now()->format('Y-m-d'));
        $sales = Auth::user()->business->sales()
            ->whereDate('created_at', $date)
            ->with('items', 'creator')
            ->latest()
            ->get();
        return response()->json($sales);
    }

    public function getMonthlySales(Request $request, $year, $month)
    {
        $sales = Auth::user()->business->sales()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->with('items', 'creator')
            ->latest()
            ->paginate(15);
        return response()->json($sales);
    }
}

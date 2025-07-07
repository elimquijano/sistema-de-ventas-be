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
    public function index()
    {
        // Gate::authorize('view-any-sale');
        $sales = Auth::user()->business->sales()->with('items', 'creator')->latest()->paginate(15);
        return response()->json($sales);
    }

    public function store(Request $request)
    {
        // Gate::authorize('create-sale');
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
            $cashRegister = $business->cashRegisters()->where('status', 'open')->first();
            if (!$cashRegister) {
                throw new \Exception('No hay una caja registradora abierta para realizar la venta.');
            }

            $sale = $business->sales()->create([
                'customer_name' => $validated['customer_name'],
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_method'] === 'credit' ? 'pending' : 'paid',
                'created_by' => Auth::id(),
                'cash_register_id' => $cashRegister->id,
                'total_amount' => 0, // Se calcula a continuación
            ]);

            $totalAmount = 0;
            foreach ($validated['items'] as $itemData) {
                $modelClass = $itemData['type'] === 'product' ? Product::class : Service::class;
                $item = $modelClass::find($itemData['id']);

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

            if ($sale->payment_method === 'credit') {
                $business->credits()->create([
                    'sale_id' => $sale->id,
                    'customer_name' => $sale->customer_name,
                    'total_amount' => $totalAmount,
                    'pending_amount' => $totalAmount,
                    'due_date' => now()->addDays(30), // Opcional: recibir de la request
                ]);
            }

            return $sale;
        });

        return response()->json($sale->load('items'), 201);
    }

    public function show(Sale $sale)
    {
        // Gate::authorize('view-sale', $sale);
        return $sale->load('items.item', 'creator', 'cashRegister');
    }

    public function destroy(Sale $sale)
    {
        // Gate::authorize('delete-sale', $sale);
        DB::transaction(function () use ($sale) {
            foreach ($sale->items as $item) {
                if ($item->item_type === Product::class) {
                    $item->item->increment('stock', $item->quantity);
                }
            }
            $sale->delete();
        });
        return response()->json(null, 204);
    }

    /**
     * Obtiene las ventas de un día específico.
     */
    public function getDailySales(Request $request, $date)
    {
        // Gate::authorize('view-any-sale');
        $sales = Auth::user()->business->sales()
            ->whereDate('created_at', $date)
            ->with('items', 'creator')
            ->latest()
            ->paginate(15);
        return response()->json($sales);
    }

    /**
     * Obtiene las ventas de un mes y año específicos.
     */
    public function getMonthlySales(Request $request, $year, $month)
    {
        // Gate::authorize('view-any-sale');
        $sales = Auth::user()->business->sales()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->with('items', 'creator')
            ->latest()
            ->paginate(15);
        return response()->json($sales);
    }
}

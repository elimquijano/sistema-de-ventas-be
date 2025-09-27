<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Service;
use App\Models\CashRegister;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

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
            'payment_method' => 'required|in:cash,credit,yape,discount',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.type' => 'required|string|in:product,service',
            'items.*.quantity' => 'required|integer|min:1',
            'yape_amount' => 'nullable|numeric|min:0',
            'yape_name' => 'nullable|string|max:255',
            'received_amount' => 'nullable|numeric|min:0',
        ]);

        $sale = DB::transaction(function () use ($validated, $business, $request) {
            $cashRegister = $business->cashRegisters()->where('status', 'open')->firstOrFail();

            $totalAmount = 0;
            foreach ($validated['items'] as $itemData) {
                $modelClass = $itemData['type'] === 'product' ? Product::class : Service::class;
                $item = $modelClass::findOrFail($itemData['id']);
                $totalAmount += $item->price * $itemData['quantity'];
            }

            $paymentMethod = $validated['payment_method'];
            $yapeAmount = $request->yape_amount ?? 0;

            if ($paymentMethod === 'yape' && $yapeAmount < $totalAmount) {
                $paymentMethod = 'cash+yape';
            }

            $sale = $business->sales()->create([
                'customer_name' => $validated['customer_name'],
                'payment_method' => $paymentMethod,
                'payment_status' => $validated['payment_method'] === 'credit' ? 'pending' : 'paid',
                'created_by' => Auth::id(),
                'cash_register_id' => $cashRegister->id,
                'total_amount' => $totalAmount,
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

                $totalPrice = $item->price * $itemData['quantity'];

                $sale->items()->create([
                    'item_id' => $item->id,
                    'item_type' => $modelClass,
                    'item_name' => $item->name,
                    'unit_price' => $item->price,
                    'quantity' => $itemData['quantity'],
                    'total_price' => $totalPrice,
                ]);
            }

            $cashRegister->increment('expected_amount', $totalAmount);

            $receivedAmount = $request->received_amount ?? 0;

            switch ($validated['payment_method']) {
                case 'cash':
                    $cashRegister->increment('cash_sales_amount', $totalAmount);
                    break;
                case 'yape':
                    if ($yapeAmount < $totalAmount) {
                        $cashRegister->increment('cash_sales_amount', $totalAmount - $yapeAmount);
                    }
                    break;
                case 'discount':
                    $discount = $totalAmount - $receivedAmount;
                    if ($discount > 0) {
                        Expense::create([
                            'business_id' => $business->id,
                            'created_by' => Auth::id(),
                            'amount' => $discount,
                            'description' => 'Descuento en venta ' . $sale->id,
                            'expense_date' => now(),
                        ]);
                    }
                    $cashRegister->increment('cash_sales_amount', $receivedAmount);
                    break;
            }

            if ($validated['payment_method'] === 'credit') {
                $business->credits()->create([
                    'sale_id' => $sale->id,
                    'customer_name' => $sale->customer_name,
                    'total_amount' => $totalAmount,
                    'pending_amount' => $totalAmount,
                    'due_date' => now()->addDays(30),
                ]);
            }

            $sale->save();

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
            if ($sale->cashRegister) {
                $sale->cashRegister->decrement('expected_amount', $sale->total_amount);
            }
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

    public function generateReceipt(Sale $sale)
    {
        if (Auth::user()->business_id !== $sale->business_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $sale->load('items', 'business', 'creator');

        $pdf = Pdf::loadView('pdf.sale_receipt', compact('sale'));

        return $pdf->stream('receipt-' . $sale->sale_number . '.pdf');
    }
}

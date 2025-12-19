<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Service;
use App\Models\CashRegister;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Auth::user()->business->sales()->with(['items', 'creator', 'business', 'payments']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('sale_number', 'like', "%{$searchTerm}%")
                    ->orWhere('customer_name', 'like', "%{$searchTerm}%");
            });
        }
        
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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

        $sales = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $business = Auth::user()->business;
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.type' => 'required|string|in:product,service',
            'items.*.quantity' => 'required|integer|min:1',
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|string|in:cash,credit,yape,plin,card,transfer,discount',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:255',
        ]);

        $sale = DB::transaction(function () use ($validated, $business) {
            $cashRegister = $business->cashRegisters()->where('status', 'open')->firstOrFail();

            $totalAmount = 0;
            foreach ($validated['items'] as $itemData) {
                $modelClass = $itemData['type'] === 'product' ? Product::class : Service::class;
                $item = $modelClass::findOrFail($itemData['id']);
                $totalAmount += $item->price * $itemData['quantity'];
            }
            
            $totalPaid = collect($validated['payments'])->sum('amount');

            if (bccomp($totalAmount, $totalPaid, 2) !== 0) {
                throw ValidationException::withMessages([
                    'payments' => 'La suma de los pagos (' . $totalPaid . ') no coincide con el monto total de la venta (' . $totalAmount . ').'
                ]);
            }

            $hasCredit = collect($validated['payments'])->contains('payment_method', 'credit');

            $sale = $business->sales()->create([
                'customer_name' => $validated['customer_name'],
                'created_by' => Auth::id(),
                'cash_register_id' => $cashRegister->id,
                'total_amount' => $totalAmount,
                'status' => $hasCredit ? 'pending' : 'completed',
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

            $cashRegister->increment('expected_amount', $totalAmount);
            
            foreach ($validated['payments'] as $payment) {
                $sale->payments()->create($payment);

                if ($payment['payment_method'] === 'cash') {
                    $cashRegister->increment('cash_sales_amount', $payment['amount']);
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

        return response()->json($sale->load('items', 'payments'), 201);
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

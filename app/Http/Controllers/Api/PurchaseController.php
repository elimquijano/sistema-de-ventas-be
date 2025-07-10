<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_name' => 'nullable|string|max:255',
            'purchase_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer|exists:products,id',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost' => 'required|numeric|min:0',
            'generate_receipt' => 'boolean',
            'receipt_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $business = Auth::user()->business;
        $totalAmount = 0;

        try {
            $purchase = DB::transaction(function () use ($validated, $business, &$totalAmount) {
                // 1. Calculate total amount
                foreach ($validated['items'] as $itemData) {
                    $totalAmount += $itemData['quantity'] * $itemData['cost'];
                }

                // 2. Create the Purchase record
                $purchase = $business->purchases()->create([
                    'created_by' => Auth::id(),
                    'supplier_name' => $validated['supplier_name'],
                    'purchase_date' => $validated['purchase_date'],
                    'total_amount' => $totalAmount,
                    'notes' => $validated['notes'],
                ]);

                // 3. Process each item: update stock or create new product
                foreach ($validated['items'] as $itemData) {
                    $product = null;
                    if (!empty($itemData['id'])) {
                        $product = Product::find($itemData['id']);
                    }

                    // If product doesn't exist, create it
                    if (!$product) {
                        $product = $business->products()->create([
                            'name' => $itemData['name'],
                            'price' => $itemData['cost'], // Initial price set to cost
                            'cost' => $itemData['cost'],
                            'stock' => $itemData['quantity'],
                            'min_stock' => 5, // Default min_stock
                            'status' => 'active',
                        ]);
                    } else {
                        // If product exists, update stock and cost
                        $product->increment('stock', $itemData['quantity']);
                        $product->update(['cost' => $itemData['cost']]);
                    }

                    // 4. Create the PurchaseItem record
                    $purchase->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $itemData['quantity'],
                        'cost' => $itemData['cost'],
                        'subtotal' => $itemData['quantity'] * $itemData['cost'],
                    ]);
                }

                // 5. Handle receipt/invoice
                $receiptPath = null;
                if ($validated['generate_receipt'] ?? false) {
                    $receiptPath = $this->generatePdfReceipt($purchase->load('items'));
                } elseif ($request->hasFile('receipt_file')) {
                    $receiptPath = $request->file('receipt_file')->store('receipts', 'public');
                }

                // 6. Create the Expense record
                $expenseCategory = $business->categories()->firstOrCreate(
                    ['name' => 'Compra de Mercadería', 'type' => 'expense'],
                    ['name' => 'Compra de Mercadería', 'type' => 'expense']
                );

                $expense = $business->expenses()->create([
                    'description' => 'Compra #' . $purchase->purchase_number . ($validated['supplier_name'] ? ' a ' . $validated['supplier_name'] : ''),
                    'amount' => $totalAmount,
                    'expense_date' => $validated['purchase_date'],
                    'category_id' => $expenseCategory->id,
                    'created_by' => Auth::id(),
                    'receipt_path' => $receiptPath,
                    'notes' => $validated['notes'],
                ]);

                // 7. Link expense to purchase
                $purchase->update(['expense_id' => $expense->id]);

                return $purchase;
            });

            return response()->json([
                'message' => 'Compra registrada exitosamente.',
                'purchase' => $purchase->load('items')
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error creating purchase: " . $e->getMessage());
            return response()->json(['message' => 'Ocurrió un error al registrar la compra.'], 500);
        }
    }

    private function generatePdfReceipt(Purchase $purchase)
    {
        $data = [
            'purchase' => $purchase,
            'business' => $purchase->business,
        ];

        $pdf = Pdf::loadView('pdf.purchase_receipt', $data);
        $filename = 'purchase-' . $purchase->purchase_number . '-' . time() . '.pdf';
        $path = 'receipts/' . $filename;

        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }
}

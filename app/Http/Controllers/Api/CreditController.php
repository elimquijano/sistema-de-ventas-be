<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Credit;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreditController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Credit::query()->with(['sale', 'creator']);

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        // Filter by customer name or sale number
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('customer_name', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('sale', function ($saleQuery) use ($searchTerm) {
                        $saleQuery->where('sale_number', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $credits = $query->latest()->paginate($perPage);

        return response()->json($credits);
    }

    public function show(Credit $credit)
    {
        // Gate::authorize('view-credit', $credit);
        return $credit->load(['sale', 'creator']);
    }

    public function update(Request $request, Credit $credit)
    {
        // Gate::authorize('update-credit', $credit);
        $validated = $request->validate([
            'due_date' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:pending,paid,overdue',
            'paid_amount' => 'sometimes|numeric|min:0|max:' . $credit->total_amount,
        ]);

        // Recalculate pending amount if paid_amount is changed
        if (isset($validated['paid_amount'])) {
            $validated['pending_amount'] = $credit->total_amount - $validated['paid_amount'];
            // Automatically update status based on new pending amount
            if ($validated['pending_amount'] <= 0) {
                $validated['status'] = 'paid';
            } else if ($credit->status === 'paid') {
                $validated['status'] = 'pending';
            }
        }

        $credit->update($validated);

        // If credit is paid, ensure the related sale is also marked as paid.
        if ($credit->status === 'paid') {
            $credit->sale()->update(['status' => 'completed']);
        } else {
            $credit->sale()->update(['status' => 'debt']);
        }

        return response()->json($credit);
    }

    public function addPayment(Request $request, Credit $credit)
    {
        // Gate::authorize('add-credit-payment', $credit);
        $validated = $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|string|in:cash,yape,plin,card,transfer,discount,vale',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:255',
            'payments.*.payment_image' => 'nullable|image',
        ]);

        $business = Auth::user()->business;
        $user = Auth::user();

        $sale = DB::transaction(function () use ($credit, $validated, $business, $user, $request) {
            $totalAmount = collect($validated['payments'])->sum('amount');

            if ($totalAmount > $credit->pending_amount + 0.05) {
                throw ValidationException::withMessages([
                    'payments' => ['El monto total de los pagos (' . $totalAmount . ') excede el monto pendiente del crédito (' . $credit->pending_amount . ').']
                ]);
            }

            $hasCash = collect($validated['payments'])->contains('payment_method', 'cash');
            $cashRegister = null;

            if ($hasCash) {
                $cashRegister = $business->cashRegisters()
                    ->where('status', 'open')
                    ->where('opened_by', $user->id)
                    ->first();

                if (!$cashRegister) {
                    throw ValidationException::withMessages([
                        'cash_register' => ['No tienes una caja registradora abierta para recibir pagos en efectivo.']
                    ]);
                }
            }

            foreach ($validated['payments'] as $index => $paymentData) {
                $imagePath = null;
                if ($request->hasFile("payments.{$index}.payment_image")) {
                    $file = $request->file("payments.{$index}.payment_image");
                    $filename = uniqid() . '.jpg';
                    $imagePath = "payments/{$filename}";

                    try {
                        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                        $image = $manager->read($file);
                        $image->scale(width: 1200);
                        $encoded = $image->toJpeg(75);
                        Storage::disk('public')->put($imagePath, (string) $encoded);
                    } catch (\Exception $e) {
                        $imagePath = $file->store('payments', 'public');
                    }
                }

                // Registrar el pago en la venta vinculada
                $credit->sale->payments()->create([
                    'amount' => $paymentData['amount'],
                    'payment_method' => $paymentData['payment_method'],
                    'reference' => $paymentData['reference'],
                    'payment_image' => $imagePath,
                ]);

                if ($paymentData['payment_method'] === 'cash' && $cashRegister) {
                    // Solo incrementamos la bolsa de cobranza y el efectivo de ventas para el efectivo físico
                    $cashRegister->increment('credit_collections', $paymentData['amount']);
                    $cashRegister->increment('cash_sales_amount', $paymentData['amount']);
                }

                // Actualizar los montos del crédito
                $credit->paid_amount += $paymentData['amount'];
                $credit->pending_amount -= $paymentData['amount'];
            }

            if ($credit->pending_amount <= 0) {
                $credit->status = 'paid';
                $credit->pending_amount = 0;
            }
            
            $credit->save();

            // Sincronizar estado de la venta
            if ($credit->status === 'paid') {
                $credit->sale()->update(['status' => 'completed']);
            }

            return $credit->sale;
        });

        return response()->json($sale->load('items', 'payments', 'client', 'rider'));
    }

    public function getPending(Request $request)
    {
        $user = Auth::user();
        $query = Credit::query()->where('status', 'pending')->with(['sale', 'creator']);

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $credits = $query->latest()->paginate($perPage);

        return response()->json($credits);
    }

    public function timeline(Credit $credit)
    {
        return response()->json($credit->getDeepTimeline());
    }
}

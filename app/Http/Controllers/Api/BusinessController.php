<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->can('view-any-business')) {
            return Business::with('owner')->paginate(15);
        }
        return Business::where('id', $user->business_id)->with('owner')->paginate(15);
    }

    public function store(Request $request)
    {
        //// Gate::authorize('create-business');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255|unique:businesses',
            'tax_id' => 'nullable|string|max:50',
            'currency' => 'required|in:PEN,USD',
        ]);

        $business = Business::create($validated + ['user_id' => Auth::id()]);
        Auth::user()->update(['business_id' => $business->id]);

        return response()->json($business, 201);
    }

    public function show(Business $business)
    {
        // Gate::authorize('view-business', $business);
        return $business->load(['products', 'services', 'sales', 'categories']);
    }

    public function update(Request $request, Business $business)
    {
        // Gate::authorize('update-business', $business);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255|unique:businesses,email,' . $business->id,
            'tax_id' => 'nullable|string|max:50',
            'currency' => 'sometimes|required|in:PEN,USD',
        ]);

        $business->update($validated);
        return response()->json($business);
    }

    public function destroy(Business $business)
    {
        // Gate::authorize('delete-business', $business);
        $business->delete();
        return response()->json(null, 204);
    }

    public function dashboard(Business $business)
    {
        // Gate::authorize('view-business-dashboard', $business);

        $stats = [
            'daily_sales' => $business->sales()->whereDate('created_at', today())->sum('total_amount'),
            'monthly_sales' => $business->sales()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total_amount'),
            'daily_expenses' => $business->expenses()->whereDate('expense_date', today())->sum('amount'),
            'monthly_expenses' => $business->expenses()->whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'products_low_stock' => $business->products()->whereColumn('stock', '<=', 'min_stock')->count(),
            'pending_credits' => $business->credits()->where('status', 'pending')->count(),
        ];

        $salesData = DB::table('sales')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as sales'))
            ->where('business_id', $business->id)
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('date')->orderBy('date', 'asc')->get();

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->select('sale_items.item_name as name', DB::raw('SUM(sale_items.quantity) as quantity'))
            ->where('sales.business_id', $business->id)
            ->where('sale_items.item_type', 'App\\Models\\Product')
            ->groupBy('sale_items.item_name')->orderBy('quantity', 'desc')->limit(5)->get();

        return response()->json([
            'stats' => $stats,
            'sales_data' => $salesData,
            'top_products' => $topProducts,
        ]);
    }
}

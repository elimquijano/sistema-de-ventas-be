<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BusinessController extends Controller
{
    // ... (index, store, show, update, destroy methods remain the same)
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
        return $business->load(['products', 'services', 'sales', 'categories']);
    }

    public function update(Request $request, Business $business)
    {
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
        $business->delete();
        return response()->json(null, 204);
    }

    public function dashboard(Request $request, Business $business)
    {
        // Set locale to Spanish for date formatting
        DB::statement("SET lc_time_names = 'es_ES'");

        $period = $request->input('period', 'week');
        [$startDate, $endDate] = $this->getDateRange($period);

        // Stats for top cards (unaffected by period filter)
        $stats = [
            'daily_sales' => $business->sales()->whereDate('created_at', today())->sum('total_amount'),
            'monthly_sales' => $business->sales()->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total_amount'),
            'daily_expenses' => $business->expenses()->whereDate('expense_date', today())->sum('amount'),
            'monthly_expenses' => $business->expenses()->whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount'),
            'products_low_stock' => $business->products()->whereColumn('stock', '<=', 'min_stock')->count(),
            'pending_credits' => $business->credits()->where('status', 'pending')->count(),
            'cash_in_register' => $business->cashRegisters()->where('status', 'open')->sum(DB::raw('initial_amount + cash_sales_amount')),
        ];

        // Data for charts (affected by period filter)
        $salesData = $this->getChartData($business, 'sales', $period, $startDate, $endDate);
        $expensesData = $this->getChartData($business, 'expenses', $period, $startDate, $endDate);

        // Totals for pie chart
        $totalSalesForPeriod = $salesData->sum('value');
        $totalExpensesForPeriod = $expensesData->sum('value');

        // Top products (last 30 days)
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->select('sale_items.item_name as name', DB::raw('SUM(sale_items.quantity) as quantity'), DB::raw('SUM(sale_items.total_price) as revenue'))
            ->where('sales.business_id', $business->id)
            ->where('sales.created_at', '>=', now()->subDays(30))
            ->where('sale_items.item_type', 'App\\Models\\Product')
            ->groupBy('sale_items.item_name')->orderBy('revenue', 'desc')->limit(5)->get();

        // Recent activities
        $recentActivities = $this->getRecentActivities($business);

        // Cash register status
        $cashRegistersToday = $business->cashRegisters()
            ->with('openedBy:id,first_name,last_name') // Eager load user info
            ->whereDate('opened_at', today())
            ->orderBy('opened_at', 'desc')
            ->get();

        return response()->json([
            'stats' => $stats,
            'chart_data' => [
                'sales' => $salesData,
                'expenses' => $expensesData,
            ],
            'pie_chart_data' => [
                ['name' => 'Ventas', 'value' => $totalSalesForPeriod],
                ['name' => 'Gastos', 'value' => $totalExpensesForPeriod],
            ],
            'top_products' => $topProducts,
            'recent_activities' => $recentActivities,
            'cash_registers_today' => $cashRegistersToday,
        ]);
    }

    private function getDateRange($period)
    {
        switch ($period) {
            case 'day':
                return [now()->startOfDay(), now()->endOfDay()];
            case 'month':
                return [now()->startOfMonth(), now()->endOfMonth()];
            case 'year':
                return [now()->startOfYear(), now()->endOfYear()];
            case 'week':
            default:
                return [now()->startOfWeek(Carbon::MONDAY), now()->endOfWeek(Carbon::SUNDAY)];
        }
    }

    private function getChartData($business, $type, $period, $startDate, $endDate)
    {
        $table = $type === 'sales' ? 'sales' : 'expenses';
        $dateColumn = $type === 'sales' ? 'created_at' : 'expense_date';
        $amountColumn = $type === 'sales' ? 'total_amount' : 'amount';

        $query = DB::table($table)
            ->where('business_id', $business->id)
            ->whereBetween($dateColumn, [$startDate, $endDate]);

        $selectSQL = "";
        $groupBySQL = "";
        $orderBySQL = "MIN({$dateColumn})";

        switch ($period) {
            case 'day':
                $selectSQL = "DATE_FORMAT({$dateColumn}, '%H:00') as label, SUM({$amountColumn}) as value";
                $groupBySQL = "label";
                break;
            case 'week':
                $selectSQL = "DATE_FORMAT({$dateColumn}, '%W') as label, SUM({$amountColumn}) as value";
                $groupBySQL = "label";
                break;
            case 'month':
                $selectSQL = "DATE_FORMAT({$dateColumn}, '%d %b') as label, SUM({$amountColumn}) as value";
                $groupBySQL = "label";
                break;
            case 'year':
                $selectSQL = "DATE_FORMAT({$dateColumn}, '%M') as label, SUM({$amountColumn}) as value";
                $groupBySQL = "label";
                $orderBySQL = "MIN(MONTH({$dateColumn}))";
                break;
        }

        return $query->select(DB::raw($selectSQL))
            ->groupBy(DB::raw($groupBySQL))
            ->orderBy(DB::raw($orderBySQL), 'asc')
            ->get();
    }

    private function getRecentActivities($business)
    {
        $sales = $business->sales()->latest()->limit(5)->get()->map(function ($sale) {
            return [
                'type' => 'sale',
                'description' => "Venta #{$sale->sale_number} a {$sale->customer_name}",
                'amount' => $sale->total_amount,
                'time' => $sale->created_at->diffForHumans(),
                'created_at' => $sale->created_at
            ];
        });

        $expenses = $business->expenses()->latest()->limit(5)->get()->map(function ($expense) {
            return [
                'type' => 'expense',
                'description' => "Gasto: {$expense->description}",
                'amount' => -$expense->amount,
                'time' => $expense->created_at->diffForHumans(),
                'created_at' => $expense->created_at
            ];
        });

        return $sales->merge($expenses)->sortByDesc('created_at')->take(5)->values();
    }
}

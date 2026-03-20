<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    // ... (index, store, show, update, destroy methods remain the same)
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Business::query()->with('owner');

        // A non-super admin can only see their own business
        if ($user->business_id) {
            $query->where('id', $user->business_id);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $businesses = $query->paginate($perPage);

        return response()->json($businesses);
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
            'email' => 'nullable|email|max:255|unique:businesses,email,'.$business->id,
            'tax_id' => 'nullable|string|max:50',
            'currency' => 'sometimes|required|in:PEN,USD',
            'user_id' => 'sometimes|required|exists:users,id',
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

        $now = Carbon::now();
        $today = Carbon::today();
        
        // Limits for daily/monthly stats using local time
        $startToday = $today->copy()->startOfDay();
        $endToday = $today->copy()->endOfDay();
        
        $startMonth = $now->copy()->startOfMonth();
        $endMonth = $now->copy()->endOfMonth();

        // Stats for top cards
        $stats = [
            'daily_sales' => $business->sales()->where('status', 'completed')->whereBetween('created_at', [$startToday, $endToday])->sum('total_amount'),
            'monthly_sales' => $business->sales()->where('status', 'completed')->whereBetween('created_at', [$startMonth, $endMonth])->sum('total_amount'),
            'daily_expenses' => $business->expenses()->whereDate('expense_date', $today)->sum('amount'),
            'monthly_expenses' => $business->expenses()->whereMonth('expense_date', $now->month)->whereYear('expense_date', $now->year)->sum('amount'),
            'products_low_stock' => $business->products()->whereColumn('stock', '<=', 'min_stock')->count(),
            'pending_credits' => $business->credits()->where('status', 'pending')->count(),
            'cash_in_register' => $business->cashRegisters()->where('status', 'open')->sum(DB::raw('initial_amount + cash_sales_amount')),
        ];

        // Data for charts
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
            ->where('sales.status', 'completed')
            ->where('sales.created_at', '>=', $now->copy()->subDays(30))
            ->where('sale_items.item_type', 'App\\Models\\Product')
            ->groupBy('sale_items.item_name')->orderBy('revenue', 'desc')->limit(5)->get();

        // Recent activities
        $recentActivities = $this->getRecentActivities($business);

        // Cash register status
        $cashRegistersToday = $business->cashRegisters()
            ->with('openedBy:id,first_name,last_name') // Eager load user info
            ->whereBetween('opened_at', [$startToday, $endToday])
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

    private function getDateRange($period, $timezone = null)
    {
        $now = Carbon::now($timezone);

        switch ($period) {
            case 'day':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
            case 'week':
            default:
                return [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY)];
        }
    }

    private function getChartData($business, $type, $period, $startDate, $endDate)
    {
        $table = $type === 'sales' ? 'sales' : 'expenses';
        $dateColumn = 'created_at';
        $amountColumn = $type === 'sales' ? 'total_amount' : 'amount';

        // Usamos las fechas directamente según la zona horaria de la aplicación
        $start = $startDate->toDateTimeString();
        $end = $endDate->toDateTimeString();

        // Usamos directamente la columna created_at sin restar horas manualmente
        $dbDateExpr = 'created_at';

        $query = DB::table($table)
            ->where('business_id', $business->id)
            ->whereBetween($dateColumn, [$start, $end]);

        if ($type === 'sales') {
            $query->where('status', 'completed');
        }

        $selectSQL = '';
        $groupBySQL = '';
        $orderBySQL = "MIN({$dbDateExpr})";

        switch ($period) {
            case 'day':
                $selectSQL = "DATE_FORMAT({$dbDateExpr}, '%H:00') as label, SUM({$amountColumn}) as value";
                $groupBySQL = 'label';
                break;
            case 'week':
                $selectSQL = "DATE_FORMAT({$dbDateExpr}, '%W') as label, SUM({$amountColumn}) as value";
                $groupBySQL = 'label';
                break;
            case 'month':
                $selectSQL = "DATE_FORMAT({$dbDateExpr}, '%d %b') as label, SUM({$amountColumn}) as value";
                $groupBySQL = 'label';
                break;
            case 'year':
                $selectSQL = "DATE_FORMAT({$dbDateExpr}, '%M') as label, SUM({$amountColumn}) as value";
                $groupBySQL = 'label';
                $orderBySQL = "MIN(MONTH({$dbDateExpr}))";
                break;
        }

        $data = $query->select(DB::raw($selectSQL))
            ->groupBy(DB::raw($groupBySQL))
            ->orderBy(DB::raw($orderBySQL), 'asc')
            ->get();

        // Post-process to fill missing dates
        return $this->fillMissingChartData($data, $period, $startDate, $endDate);
    }

    private function fillMissingChartData($data, $period, $startDate, $endDate)
    {
        $filledData = collect();
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $label = '';
            switch ($period) {
                case 'day':
                    $label = $currentDate->format('H:00');
                    $increment = 'addHour';
                    break;
                case 'week':
                    $label = $currentDate->locale('es')->isoFormat('dddd');
                    $increment = 'addDay';
                    break;
                case 'month':
                    // We strip the trailing dot from Carbon's abbreviated month if it exists
                    $label = rtrim($currentDate->locale('es')->isoFormat('DD MMM'), '.');
                    $increment = 'addDay';
                    break;
                case 'year':
                    $label = $currentDate->locale('es')->isoFormat('MMMM');
                    $increment = 'addMonth';
                    break;
            }

            // Find matching data or default to 0
            $existing = $data->first(function ($item) use ($label) {
                return strtolower(trim($item->label)) === strtolower(trim($label));
            });

            $filledData->push([
                'label' => $label,
                'value' => $existing ? $existing->value : 0,
            ]);

            $currentDate->$increment();
        }

        return $filledData;
    }

    private function getRecentActivities($business, $timezone = 'UTC')
    {
        $sales = $business->sales()->latest()->limit(5)->get()->toBase()->map(function ($sale) {
            return [
                'type' => 'sale',
                'description' => "Venta #{$sale->sale_number} a {$sale->customer_name}",
                'amount' => $sale->total_amount,
                'time' => $sale->created_at->locale('es')->diffForHumans(),
                'created_at' => $sale->created_at,
            ];
        });

        $expenses = $business->expenses()->latest()->limit(5)->get()->toBase()->map(function ($expense) {
            return [
                'type' => 'expense',
                'description' => "Gasto: {$expense->description}",
                'amount' => -$expense->amount,
                'time' => $expense->created_at->locale('es')->diffForHumans(),
                'created_at' => $expense->created_at,
            ];
        });

        return $sales->merge($expenses)->sortByDesc('created_at')->take(5)->values();
    }
}

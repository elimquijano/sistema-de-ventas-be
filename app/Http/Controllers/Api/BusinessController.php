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
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Business::query()->with('owner');

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
        // No retornar relaciones pesadas para mejorar el rendimiento
        return $business;
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
        DB::statement("SET lc_time_names = 'es_ES'");

        $period = $request->input('period', 'week');
        [$startDate, $endDate] = $this->getDateRange($period);

        $now = Carbon::now();
        $today = Carbon::today();
        $startToday = $today->copy()->startOfDay();
        $endToday = $today->copy()->endOfDay();

        // 1. Alertas y Notificaciones (Regidas por el periodo cuando aplique)
        $stats = [
            'products_low_stock' => $business->products()->whereColumn('stock', '<=', 'min_stock')->count(),
            'pending_credits' => $business->credits()->where('status', 'pending')->count(),
            'active_asset_loans' => $business->assetLoans()->where('status', 'loaned')->count(), // Bienes prestados actualmente
            'period_asset_loans' => $business->assetLoans()->whereBetween('created_at', [$startDate, $endDate])->count(), // Bienes prestados en el periodo
        ];

        // 2. Histogramas (Ventas vs Gastos en el periodo)
        $salesData = $this->getChartData($business, 'sales', $period, $startDate, $endDate);
        $expensesData = $this->getChartData($business, 'expenses', $period, $startDate, $endDate);

        // 3. Ganancia Neta y Promedios (Basado en cajas cuyo inicio está en el periodo)
        $profitStats = $business->cashRegisters()
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->select(
                DB::raw('SUM(profit) as total_profit'),
                DB::raw('AVG(profit) as avg_profit'),
                DB::raw('COUNT(*) as total_registers')
            )->first();

        // 4. Ganancia por Usuario (Profit por caja iniciada por usuario en el periodo)
        $profitByUser = DB::table('cash_registers')
            ->join('users', 'cash_registers.opened_by', '=', 'users.id')
            ->select(
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) as name"),
                DB::raw('SUM(cash_registers.profit) as value')
            )
            ->where('cash_registers.business_id', $business->id)
            ->whereBetween('cash_registers.opened_at', [$startDate, $endDate])
            ->groupBy('users.id', 'users.first_name', 'users.last_name')
            ->orderBy('value', 'desc')
            ->get();

        // 5. Gasto por Categoría (En qué se gasta más en el periodo)
        $expensesByCategory = DB::table('expenses')
            ->join('categories', 'expenses.category_id', '=', 'categories.id')
            ->select('categories.name as name', DB::raw('SUM(expenses.amount) as value'))
            ->where('expenses.business_id', $business->id)
            ->whereBetween('expenses.expense_date', [$startDate, $endDate])
            ->whereNull('expenses.deleted_at')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('value', 'desc')
            ->get();

        // 6. Formas de Pago (Distribución según el periodo)
        $paymentMethods = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->select('sale_payments.payment_method as name', DB::raw('SUM(sale_payments.amount) as value'))
            ->where('sales.business_id', $business->id)
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->whereNull('sales.deleted_at')
            ->whereNull('sale_payments.deleted_at')
            ->groupBy('sale_payments.payment_method')
            ->get();

        // 7. Top 5 Productos (Ajustado al periodo)
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->select('sale_items.item_name as name', DB::raw('SUM(sale_items.quantity) as quantity'), DB::raw('SUM(sale_items.total_price) as revenue'))
            ->where('sales.business_id', $business->id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->whereNull('sales.deleted_at')
            ->whereNull('sale_items.deleted_at')
            ->where('sale_items.item_type', 'App\\Models\\Product')
            ->groupBy('sale_items.item_name')->orderBy('revenue', 'desc')->limit(5)->get();

        // 8. Top 5 Clientes (Ajustado al periodo)
        $topClients = DB::table('sales')
            ->select('customer_name as name', DB::raw('SUM(total_amount) as value'), DB::raw('COUNT(*) as orders'))
            ->where('business_id', $business->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->groupBy('customer_name')
            ->orderBy('value', 'desc')
            ->limit(5)
            ->get();

        // 9. Comparativa con Periodo Anterior
        $comparison = $this->getPeriodComparison($business, $period, $startDate, $endDate);

        // 10. Cajas de Hoy (Se mantiene como "Hoy" por ser operativo inmediato)
        $cashRegistersToday = $business->cashRegisters()
            ->with('openedBy:id,first_name,last_name')
            ->whereBetween('opened_at', [$startToday, $endToday])
            ->orderBy('opened_at', 'desc')
            ->get();

        return response()->json([
            'period_info' => [
                'label' => $period,
                'start' => $startDate->toDateTimeString(),
                'end' => $endDate->toDateTimeString(),
            ],
            'stats' => $stats,
            'financials' => [
                'net_profit' => (float) ($profitStats->total_profit ?? 0),
                'avg_profit_per_register' => (float) ($profitStats->avg_profit ?? 0),
                'total_sales' => $salesData->sum('value'),
                'total_expenses' => $expensesData->sum('value'),
                'growth_comparison' => $comparison,
            ],
            'charts' => [
                'histogram' => [
                    'sales' => $salesData,
                    'expenses' => $expensesData,
                ],
                'profit_by_user' => $profitByUser,
                'expenses_by_category' => $expensesByCategory,
                'payment_methods' => $paymentMethods,
                'top_clients' => $topClients,
            ],
            'top_products' => $topProducts,
            'cash_registers_today' => $cashRegistersToday,
        ]);
    }

    private function getPeriodComparison($business, $period, $startDate, $endDate)
    {
        $diff = $startDate->diffInDays($endDate) + 1;
        $prevStartDate = $startDate->copy()->subDays($diff);
        $prevEndDate = $endDate->copy()->subDays($diff);

        $currentSales = DB::table('sales')
            ->where('business_id', $business->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->sum('total_amount');

        $prevSales = DB::table('sales')
            ->where('business_id', $business->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->whereNull('deleted_at')
            ->sum('total_amount');

        $growth = $prevSales > 0 ? (($currentSales - $prevSales) / $prevSales) * 100 : ($currentSales > 0 ? 100 : 0);

        return [
            'current_sales' => (float) $currentSales,
            'previous_sales' => (float) $prevSales,
            'growth_percentage' => round($growth, 2),
            'trend' => $growth >= 0 ? 'up' : 'down',
        ];
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
        $dateColumn = $type === 'sales' ? 'created_at' : 'expense_date';
        $amountColumn = $type === 'sales' ? 'total_amount' : 'amount';

        $start = $startDate->toDateTimeString();
        $end = $endDate->toDateTimeString();

        $query = DB::table($table)
            ->where('business_id', $business->id)
            ->whereNull('deleted_at')
            ->whereBetween($dateColumn, [$start, $end]);

        if ($type === 'sales') {
            $query->where('status', 'completed');
        }

        $selectSQL = '';
        $groupBySQL = '';
        $orderBySQL = "MIN({$dateColumn})";

        switch ($period) {
            case 'day':
                $selectSQL = "DATE_FORMAT({$dateColumn}, '%H:00') as label, SUM({$amountColumn}) as value";
                $groupBySQL = 'label';
                break;
            case 'week':
                $selectSQL = "DATE_FORMAT({$dateColumn}, '%W') as label, SUM({$amountColumn}) as value";
                $groupBySQL = 'label';
                break;
            case 'month':
                $selectSQL = "DATE_FORMAT({$dateColumn}, '%d %b') as label, SUM({$amountColumn}) as value";
                $groupBySQL = 'label';
                break;
            case 'year':
                $selectSQL = "DATE_FORMAT({$dateColumn}, '%M') as label, SUM({$amountColumn}) as value";
                $groupBySQL = 'label';
                $orderBySQL = "MIN(MONTH({$dateColumn}))";
                break;
        }

        $data = $query->select(DB::raw($selectSQL))
            ->groupBy(DB::raw($groupBySQL))
            ->orderBy(DB::raw($orderBySQL), 'asc')
            ->get();

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
                    $label = rtrim($currentDate->locale('es')->isoFormat('DD MMM'), '.');
                    $increment = 'addDay';
                    break;
                case 'year':
                    $label = $currentDate->locale('es')->isoFormat('MMMM');
                    $increment = 'addMonth';
                    break;
            }

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
}

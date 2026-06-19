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
            'logo_path' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'zoom' => 'nullable|integer|between:0,22',
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
            'logo' => 'nullable|image|max:2048', // Validación para el archivo
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'zoom' => 'nullable|integer|between:0,22',
        ]);

        if ($request->hasFile('logo')) {
            // Eliminar logo anterior si existe
            if ($business->logo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($business->logo_path);
            }
            // Guardar nuevo logo
            $path = $request->file('logo')->store('logos', 'public');
            $validated['logo_path'] = $path;
        }

        unset($validated['logo']);

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
        [$startDate, $endDate] = $this->getDateRange($period, $request);

        // Periodo Anterior para comparativas
        [$prevStartDate, $prevEndDate] = $this->getPreviousDateRange($period, $startDate, $endDate);

        // Métricas actuales y anteriores
        $currentMetrics = $this->getMetricsForRange($business, $startDate, $endDate);
        $prevMetrics = $this->getMetricsForRange($business, $prevStartDate, $prevEndDate);

        // 1. Alertas y Notificaciones
        $stats = [
            'products_low_stock' => $business->products()->whereColumn('stock', '<=', 'min_stock')->count(),
            'pending_credits' => $business->credits()->where('status', 'pending')->count(),
            'active_asset_loans' => $business->assetLoans()->where('status', 'loaned')->count(),
            'period_asset_loans' => $business->assetLoans()->whereBetween('created_at', [$startDate, $endDate])->count(),
        ];

        // 2. Histogramas
        $salesData = $this->getChartData($business, 'sales', $period, $startDate, $endDate);
        $expensesData = $this->getChartData($business, 'expenses', $period, $startDate, $endDate);

        // 3. Cálculo de Promedio de Ganancias (Profit)
        $avgProfit = $this->calculateAverageProfit($currentMetrics['profit'], $period, $startDate, $endDate);

        // 4. Ganancia por Usuario
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

        // 5. Gasto por Categoría
        $expensesByCategory = DB::table('expenses')
            ->join('categories', 'expenses.category_id', '=', 'categories.id')
            ->select('categories.name as name', DB::raw('SUM(expenses.amount) as value'))
            ->where('expenses.business_id', $business->id)
            ->whereBetween('expenses.expense_date', [$startDate, $endDate])
            ->whereNull('expenses.deleted_at')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('value', 'desc')
            ->get();

        // 6. Formas de Pago
        $paymentMethods = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->select('sale_payments.payment_method as name', DB::raw('SUM(sale_payments.amount) as value'))
            ->where('sales.business_id', $business->id)
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->whereNull('sales.deleted_at')
            ->whereNull('sale_payments.deleted_at')
            ->groupBy('sale_payments.payment_method')
            ->get();

        // 7. Top 5 Productos (Corregido: Revenue = Ventas, Cost = Gasto mercadería)
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('products', function($join) {
                $join->on('sale_items.item_id', '=', 'products.id')
                     ->where('sale_items.item_type', '=', 'App\\Models\\Product');
            })
            ->select(
                'sale_items.item_name as name',
                DB::raw('SUM(sale_items.quantity) as quantity'),
                DB::raw('SUM(sale_items.total_price) as revenue'),
                DB::raw('SUM(COALESCE(products.cost, 0) * sale_items.quantity) as cost')
            )
            ->where('sales.business_id', $business->id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.scheduled_at', [$startDate, $endDate])
            ->whereNull('sales.deleted_at')
            ->whereNull('sale_items.deleted_at')
            ->groupBy('sale_items.item_name')
            ->orderBy('revenue', 'desc')
            ->limit(5)
            ->get();

        // 8. Top 5 Clientes
        $topClients = DB::table('sales')
            ->select('customer_name as name', DB::raw('SUM(total_amount) as value'), DB::raw('COUNT(*) as orders'))
            ->where('business_id', $business->id)
            ->where('status', 'completed')
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->groupBy('customer_name')
            ->orderBy('value', 'desc')
            ->limit(5)
            ->get();

        // 9. Cajas de Hoy
        $today = Carbon::today();
        $cashRegistersToday = $business->cashRegisters()
            ->with('openedBy:id,first_name,last_name')
            ->whereBetween('opened_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
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
                'profit' => $this->formatMetric($currentMetrics['profit'], $prevMetrics['profit']),
                'sales' => $this->formatMetric($currentMetrics['sales'], $prevMetrics['sales']),
                'expenses' => $this->formatMetric($currentMetrics['expenses'], $prevMetrics['expenses']),
                'cost_of_goods_sold' => $this->formatMetric($currentMetrics['cogs'], $prevMetrics['cogs']),
                'avg_profit' => (float) $avgProfit,
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

    private function getPreviousDateRange($period, $startDate, $endDate)
    {
        $diff = $startDate->diffInDays($endDate) + 1;
        
        switch ($period) {
            case 'day':
                $prevStart = $startDate->copy()->subDay();
                $prevEnd = $endDate->copy()->subDay();
                break;
            case 'week':
                $prevStart = $startDate->copy()->subWeek();
                $prevEnd = $endDate->copy()->subWeek();
                break;
            case 'month':
                $prevStart = $startDate->copy()->subMonth();
                $prevEnd = $endDate->copy()->subMonth();
                break;
            case 'year':
                $prevStart = $startDate->copy()->subYear();
                $prevEnd = $endDate->copy()->subYear();
                break;
            default:
                $prevStart = $startDate->copy()->subDays($diff);
                $prevEnd = $endDate->copy()->subDays($diff);
                break;
        }

        return [$prevStart, $prevEnd];
    }

    private function getMetricsForRange($business, $startDate, $endDate)
    {
        $start = $startDate->toDateTimeString();
        $end = $endDate->toDateTimeString();

        $sales = DB::table('sales')
            ->where('business_id', $business->id)
            ->where('status', 'completed')
            ->whereBetween('scheduled_at', [$start, $end])
            ->whereNull('deleted_at')
            ->sum('total_amount');

        $expenses = DB::table('expenses')
            ->where('business_id', $business->id)
            ->whereBetween('expense_date', [$start, $end])
            ->whereNull('deleted_at')
            ->sum('amount');

        $profit = DB::table('cash_registers')
            ->where('business_id', $business->id)
            ->whereBetween('opened_at', [$start, $end])
            ->sum('profit');

        $cogs = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('products', function($join) {
                $join->on('sale_items.item_id', '=', 'products.id')
                     ->where('sale_items.item_type', '=', 'App\\Models\\Product');
            })
            ->where('sales.business_id', $business->id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.scheduled_at', [$start, $end])
            ->whereNull('sales.deleted_at')
            ->whereNull('sale_items.deleted_at')
            ->sum(DB::raw('COALESCE(products.cost, 0) * sale_items.quantity'));

        return [
            'sales' => (float)$sales,
            'expenses' => (float)$expenses,
            'profit' => (float)$profit,
            'cogs' => (float)$cogs,
        ];
    }

    private function formatMetric($current, $previous)
    {
        $growth = $previous > 0 ? (($current - $previous) / $previous) * 100 : ($current > 0 ? 100 : 0);

        return [
            'value' => (float) $current,
            'previous' => (float) $previous,
            'percentage' => round($growth, 2),
            'trend' => $growth >= 0 ? 'up' : 'down',
        ];
    }

    private function calculateAverageProfit($totalProfit, $period, $startDate, $endDate)
    {
        $diffDays = $startDate->diffInDays($endDate) + 1;

        switch ($period) {
            case 'day':
                return $totalProfit / 24;
            case 'week':
            case 'month':
                return $totalProfit / $diffDays;
            case 'year':
                return $totalProfit / 12;
            case 'custom':
                if ($startDate->isSameDay($endDate)) {
                    return $totalProfit / 24;
                }
                return $totalProfit / $diffDays;
            default:
                return $totalProfit / $diffDays;
        }
    }

    private function getDateRange($period, $request = null)
    {
        $now = Carbon::now();

        // Si el periodo parece una fecha (YYYY-MM-DD), tratarlo como un día específico
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            $date = Carbon::parse($period);
            return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
        }

        switch ($period) {
            case 'custom':
                if ($request && ($request->has('from') || $request->has('to'))) {
                    $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : $now->copy()->startOfMonth();
                    $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : $now->copy()->endOfDay();
                    return [$from, $to];
                }
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
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
        $dateColumn = $type === 'sales' ? 'scheduled_at' : 'expense_date';
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

        // Determinar agrupación para periodo personalizado o fecha específica
        $standardPeriods = ['day', 'week', 'month', 'year'];
        if (!in_array($period, $standardPeriods)) {
            $daysDiff = $startDate->diffInDays($endDate);
            if ($daysDiff <= 1) {
                $period = 'day';
            } elseif ($daysDiff <= 31) {
                $period = 'month'; // Agrupación diaria (formato DD MMM)
            } else {
                $period = 'year'; // Agrupación mensual
            }
        }

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

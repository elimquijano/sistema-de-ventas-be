<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\StoreSalaryAdvanceRequest;
use App\Models\AttendanceLog;
use App\Models\SalaryAdvance;
use App\Models\User;
use App\Models\UserPayrollConfig;
use App\Models\Expense;
use App\Models\Category;
use App\Models\PayrollPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PayrollController extends Controller
{
    // --- Configuración de Nómina ---

    public function setConfig(Request $request, User $user)
    {
        $validated = $request->validate([
            'base_salary' => 'required|numeric|min:0',
            'payment_frequency' => 'required|in:daily,weekly,monthly',
            'work_schedule' => 'nullable|array',
        ]);

        $config = UserPayrollConfig::updateOrCreate(
            ['user_id' => $user->id, 'business_id' => Auth::user()->business_id],
            $validated
        );

        return response()->json($config);
    }

    public function getConfig(User $user)
    {
        $config = UserPayrollConfig::where('user_id', $user->id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        return response()->json($config);
    }

    // --- Asistencia ---

    public function storeAttendance(StoreAttendanceRequest $request)
    {
        $attendance = AttendanceLog::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'date' => $request->date,
                'business_id' => Auth::user()->business_id
            ],
            ['status' => $request->status, 'notes' => $request->notes]
        );

        return response()->json($attendance);
    }

    public function getAttendance(Request $request)
    {
        $query = AttendanceLog::where('business_id', Auth::user()->business_id);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        return response()->json($query->get());
    }

    // --- Adelantos ---

    public function storeAdvance(StoreSalaryAdvanceRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $user = Auth::user();
                $business = $user->business;
                $targetUser = User::findOrFail($request->user_id);

                // 1. Create the advance
                $advance = SalaryAdvance::create(array_merge($request->validated(), [
                    'business_id' => $business->id,
                    'status' => 'pending'
                ]));

                // 2. Create the Expense record
                $expenseCategory = Category::firstOrCreate(
                    ['name' => 'Adelanto de Sueldo', 'type' => 'expense', 'business_id' => $business->id],
                    ['name' => 'Adelanto de Sueldo', 'type' => 'expense', 'business_id' => $business->id]
                );

                $expense = Expense::create([
                    'business_id' => $business->id,
                    'description' => 'Adelanto de sueldo: ' . $targetUser->full_name,
                    'amount' => $request->amount,
                    'expense_date' => $request->date,
                    'category_id' => $expenseCategory->id,
                    'created_by' => $user->id,
                    'notes' => $request->description,
                ]);

                // 3. Link expense to advance
                $advance->update(['expense_id' => $expense->id]);

                return response()->json($advance, 201);
            });
        } catch (\Exception $e) {
            Log::error("Error creating salary advance: " . $e->getMessage());
            return response()->json(['message' => 'Ocurrió un error al registrar el adelanto.'], 500);
        }
    }

    public function getAdvances(Request $request)
    {
        $query = SalaryAdvance::where('business_id', Auth::user()->business_id);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    // --- Cálculo de Planilla (Resumen) ---

    public function calculate(Request $request, User $user)
    {
        $business_id = Auth::user()->business_id;

        $config = UserPayrollConfig::where('user_id', $user->id)
            ->where('business_id', $business_id)
            ->first();
        
        if (!$config) {
            return response()->json(['message' => 'El usuario no tiene configuración de planilla.'], 422);
        }

        // 1. Fecha de fin (por defecto hoy)
        $endDate = $request->filled('end_date') 
            ? Carbon::parse($request->end_date)->startOfDay() 
            : now()->startOfDay();

        // 2. Detectar fecha de inicio
        $lastPayment = PayrollPayment::where('user_id', $user->id)
            ->where('business_id', $business_id)
            ->latest('end_date')
            ->first();

        if ($lastPayment) {
            $startDate = Carbon::parse($lastPayment->end_date)->addDay()->startOfDay();
        } else {
            // Si es el primer pago, retrocedemos según la frecuencia desde el endDate
            $startDate = $endDate->copy();
            if ($config->payment_frequency === 'weekly') {
                $startDate->subDays(6);
            } elseif ($config->payment_frequency === 'monthly') {
                $startDate->subDays(29);
            }
            $startDate->startOfDay();
        }

        if ($startDate->gt($endDate)) {
            return response()->json([
                'message' => 'El periodo ya ha sido pagado hasta el ' . ($lastPayment ? $lastPayment->end_date : 'la fecha de creación'),
                'last_payment_end_date' => $lastPayment ? $lastPayment->end_date : null,
            ], 422);
        }

        // 3. Obtener adelantos pendientes
        $advances = SalaryAdvance::where('user_id', $user->id)
            ->where('business_id', $business_id)
            ->where('status', 'pending')
            ->where('date', '<=', $endDate->toDateString())
            ->get();

        $totalAdvances = $advances->sum('amount');

        // 4. Asistencia Automática: Días totales del periodo - Faltas registradas
        // Usamos diffInDays con startOfDay en ambos para asegurar un entero exacto
        $totalDaysInPeriod = (int) $startDate->diffInDays($endDate) + 1;
        
        $absences = AttendanceLog::where('user_id', $user->id)
            ->where('business_id', $business_id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('status', 'absent')
            ->get();

        $absencesCount = $absences->count();
        $daysWorked = max(0, $totalDaysInPeriod - $absencesCount);

        // 5. Lógica de cálculo basada en el tipo de sueldo
        $dailyRate = 0;
        $calculatedSalary = 0;

        if ($config->payment_frequency === 'daily') {
            $dailyRate = $config->base_salary;
            $calculatedSalary = $daysWorked * $dailyRate;
        } else {
            // Semanal o Mensual: Se calcula el proporcional diario
            $divisor = ($config->payment_frequency === 'monthly') ? 30 : 7;
            $dailyRate = $config->base_salary / $divisor;
            $calculatedSalary = $daysWorked * $dailyRate;
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
            ],
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'total_days' => $totalDaysInPeriod,
            ],
            'config' => $config,
            'summary' => [
                'days_worked' => $daysWorked,
                'absences' => $absencesCount,
                'base_salary' => $config->base_salary,
                'daily_rate' => round($dailyRate, 2),
                'gross_salary' => round($calculatedSalary, 2),
                'advances_to_discount' => round($totalAdvances, 2),
                'final_payment' => round(max(0, $calculatedSalary - $totalAdvances), 2),
            ],
            'advances_details' => $advances,
            'absences_details' => $absences
        ]);
    }

    // --- Pago de Nómina ---

    public function pay(Request $request, User $user)
    {
        $validated = $request->validate([
            'end_date' => 'required|date',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($request, $user, $validated) {
                $auth = Auth::user();
                $business = $auth->business;

                // 1. Obtener el cálculo exacto (reutilizamos la lógica de calculate)
                $calculationResponse = $this->calculate($request, $user);
                $calculation = json_decode($calculationResponse->getContent(), true);

                if (!isset($calculation['summary'])) {
                    return $calculationResponse;
                }

                $summary = $calculation['summary'];
                $advances = $calculation['advances_details'];

                // 2. Crear el registro en Gastos (Outflow)
                $expenseCategory = Category::firstOrCreate(
                    ['name' => 'Pago de Nómina', 'type' => 'expense', 'business_id' => $business->id],
                    ['name' => 'Pago de Nómina', 'type' => 'expense', 'business_id' => $business->id]
                );

                $expense = Expense::create([
                    'business_id' => $business->id,
                    'description' => 'Pago de nómina: ' . $user->full_name . ' (' . $calculation['period']['start'] . ' - ' . $calculation['period']['end'] . ')',
                    'amount' => $summary['final_payment'],
                    'expense_date' => $validated['payment_date'],
                    'category_id' => $expenseCategory->id,
                    'created_by' => $auth->id,
                    'notes' => $validated['notes'],
                ]);

                // 3. Crear el pago de nómina
                $payment = PayrollPayment::create([
                    'user_id' => $user->id,
                    'business_id' => $business->id,
                    'expense_id' => $expense->id,
                    'base_salary' => $summary['base_salary'],
                    'advances_discounted' => $summary['advances_to_discount'],
                    'final_payment' => $summary['final_payment'],
                    'start_date' => $calculation['period']['start'],
                    'end_date' => $calculation['period']['end'],
                    'payment_date' => $validated['payment_date'],
                    'notes' => $validated['notes'],
                ]);

                // 4. Marcar adelantos como descontados y vincularlos al pago
                foreach ($advances as $advanceData) {
                    $advance = SalaryAdvance::find($advanceData['id']);
                    if ($advance) {
                        $advance->update([
                            'status' => 'discounted',
                            'payroll_payment_id' => $payment->id
                        ]);
                    }
                }

                return response()->json([
                    'message' => 'Pago de nómina registrado exitosamente.',
                    'payment' => $payment->load('user')
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error("Error paying payroll: " . $e->getMessage());
            return response()->json(['message' => 'Ocurrió un error al procesar el pago: ' . $e->getMessage()], 500);
        }
    }

    public function getPayments(Request $request)
    {
        $query = PayrollPayment::where('business_id', Auth::user()->business_id)->with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('payment_date', [$request->start_date, $request->end_date]);
        }

        return response()->json($query->latest()->get());
    }
}

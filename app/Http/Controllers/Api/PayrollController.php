<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\StoreSalaryAdvanceRequest;
use App\Models\AttendanceLog;
use App\Models\Category;
use App\Models\Expense;
use App\Models\PayrollPayment;
use App\Models\SalaryAdvance;
use App\Models\User;
use App\Models\UserPayrollConfig;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollController extends Controller
{
    public function setConfig(Request $request, User $user)
    {
        $this->ensureBusinessUser($user);
        $validated = $request->validate([
            'base_salary' => 'required|numeric|min:0',
            'payment_frequency' => 'required|in:daily,weekly,monthly',
            'start_date' => 'nullable|date',
            'work_schedule' => 'nullable|array',
        ]);

        $config = UserPayrollConfig::updateOrCreate(
            ['user_id' => $user->id, 'business_id' => $this->businessId()],
            $validated
        );

        return response()->json($config);
    }

    public function getConfig(User $user)
    {
        $this->ensureBusinessUser($user);

        return response()->json(UserPayrollConfig::where('user_id', $user->id)
            ->where('business_id', $this->businessId())
            ->firstOrFail());
    }

    public function storeAttendance(StoreAttendanceRequest $request)
    {
        $targetUser = User::findOrFail($request->integer('user_id'));
        $this->ensureBusinessUser($targetUser);
        $attendance = AttendanceLog::withTrashed()
            ->where('user_id', $targetUser->id)
            ->whereDate('date', $request->date)
            ->first() ?? new AttendanceLog([
                'user_id' => $targetUser->id,
                'date' => $request->date,
            ]);
        $wasNew = ! $attendance->exists;
        $wasTrashed = $attendance->exists && $attendance->trashed();
        $attendance->fill([
            'business_id' => $this->businessId(),
            'status' => $request->status,
            'notes' => $request->notes,
        ])->save();
        if ($wasTrashed) {
            $attendance->restore();
        }

        return response()->json($attendance->fresh(), $wasNew ? 201 : 200);
    }

    public function storeBulkAbsences(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
            'overwrite' => 'sometimes|boolean',
            'exclude_dates' => 'sometimes|array',
            'exclude_dates.*' => 'date|distinct',
        ]);
        $targetUser = User::findOrFail($validated['user_id']);
        $this->ensureBusinessUser($targetUser);
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->startOfDay();

        if ((int) $startDate->diffInDays($endDate) + 1 > 366) {
            return response()->json(['message' => 'El rango maximo permitido es de 366 dias.'], 422);
        }

        $excludedDates = collect($validated['exclude_dates'] ?? [])
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();
        $overwrite = (bool) ($validated['overwrite'] ?? false);

        $result = DB::transaction(function () use ($targetUser, $startDate, $endDate, $excludedDates, $overwrite, $validated) {
            $counts = ['created' => 0, 'updated' => 0, 'restored' => 0, 'skipped' => 0];
            $records = collect();

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateString = $date->toDateString();
                if ($excludedDates->has($dateString)) {
                    $counts['skipped']++;

                    continue;
                }

                $attendance = AttendanceLog::withTrashed()
                    ->where('user_id', $targetUser->id)
                    ->whereDate('date', $dateString)
                    ->first();

                if ($attendance && ! $attendance->trashed() && ! $overwrite) {
                    $counts['skipped']++;
                    $records->push($attendance);

                    continue;
                }

                if (! $attendance) {
                    $attendance = new AttendanceLog;
                    $counts['created']++;
                } elseif ($attendance->trashed()) {
                    $attendance->restore();
                    $counts['restored']++;
                } else {
                    $counts['updated']++;
                }

                $attendance->fill([
                    'user_id' => $targetUser->id,
                    'business_id' => $this->businessId(),
                    'date' => $dateString,
                    'status' => 'absent',
                    'notes' => $validated['notes'] ?? null,
                ])->save();
                $records->push($attendance->fresh());
            }

            return ['counts' => $counts, 'records' => $records->values()];
        });

        return response()->json([
            'message' => 'Faltas procesadas correctamente.',
            'user_id' => $targetUser->id,
            'range' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            ...$result,
        ], 201);
    }

    public function getAttendance(Request $request)
    {
        $request->validate([
            'user_id' => 'sometimes|integer',
            'status' => 'sometimes|in:worked,absent,day_off,sick,holiday',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);
        $query = AttendanceLog::where('business_id', $this->businessId())->with('user');
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        return response()->json($query->orderByDesc('date')->orderByDesc('id')->get());
    }

    public function showAttendance(AttendanceLog $attendanceLog)
    {
        $this->ensureBusinessRecord($attendanceLog);

        return response()->json($attendanceLog->load('user'));
    }

    public function updateAttendance(Request $request, AttendanceLog $attendanceLog)
    {
        $this->ensureBusinessRecord($attendanceLog);
        $validated = $request->validate([
            'user_id' => 'sometimes|required|integer|exists:users,id',
            'date' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:worked,absent,day_off,sick,holiday',
            'notes' => 'sometimes|nullable|string',
        ]);
        $targetUser = isset($validated['user_id']) ? User::findOrFail($validated['user_id']) : $attendanceLog->user;
        $this->ensureBusinessUser($targetUser);
        $date = $validated['date'] ?? $attendanceLog->date->toDateString();
        $duplicate = AttendanceLog::withTrashed()
            ->where('user_id', $targetUser->id)
            ->whereDate('date', $date)
            ->whereKeyNot($attendanceLog->id)
            ->exists();
        if ($duplicate) {
            return response()->json(['message' => 'Ya existe una asistencia para ese empleado y fecha.'], 422);
        }

        $attendanceLog->fill($validated);
        $attendanceLog->user_id = $targetUser->id;
        $attendanceLog->business_id = $this->businessId();
        $attendanceLog->save();

        return response()->json($attendanceLog->fresh()->load('user'));
    }

    public function destroyAttendance(AttendanceLog $attendanceLog)
    {
        $this->ensureBusinessRecord($attendanceLog);
        $attendanceLog->delete();

        return response()->json(['message' => 'Registro de asistencia eliminado.']);
    }

    public function storeAdvance(StoreSalaryAdvanceRequest $request)
    {
        $targetUser = User::findOrFail($request->integer('user_id'));
        $this->ensureBusinessUser($targetUser);

        try {
            $advance = DB::transaction(function () use ($request, $targetUser) {
                $advance = SalaryAdvance::create([
                    ...$request->validated(),
                    'business_id' => $this->businessId(),
                    'status' => 'pending',
                ]);
                $expense = $this->createAdvanceExpense(
                    $targetUser,
                    (float) $request->amount,
                    $request->date,
                    $request->description
                );
                $advance->update(['expense_id' => $expense->id]);

                return $advance->fresh()->load(['user', 'expense']);
            });

            return response()->json($advance, 201);
        } catch (\Throwable $exception) {
            Log::error('Error creating salary advance: '.$exception->getMessage());

            return response()->json(['message' => 'Ocurrio un error al registrar el adelanto.'], 500);
        }
    }

    public function getAdvances(Request $request)
    {
        $request->validate([
            'user_id' => 'sometimes|integer',
            'status' => 'sometimes|in:pending,discounted',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);
        $query = SalaryAdvance::where('business_id', $this->businessId())
            ->with(['user', 'expense', 'payrollPayment', 'originPayrollPayment']);
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        return response()->json($query->latest('date')->latest('id')->get());
    }

    public function showAdvance(SalaryAdvance $salaryAdvance)
    {
        $this->ensureBusinessRecord($salaryAdvance);

        return response()->json($salaryAdvance->load(['user', 'expense', 'payrollPayment', 'originPayrollPayment']));
    }

    public function updateAdvance(Request $request, SalaryAdvance $salaryAdvance)
    {
        $this->ensureBusinessRecord($salaryAdvance);
        if ($salaryAdvance->status !== 'pending') {
            return response()->json(['message' => 'Solo se pueden editar adelantos pendientes.'], 422);
        }
        $validated = $request->validate([
            'user_id' => 'sometimes|required|integer|exists:users,id',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'date' => 'sometimes|required|date',
            'description' => 'sometimes|nullable|string|max:255',
        ]);
        $targetUser = isset($validated['user_id']) ? User::findOrFail($validated['user_id']) : $salaryAdvance->user;
        $this->ensureBusinessUser($targetUser);

        $advance = DB::transaction(function () use ($salaryAdvance, $validated, $targetUser) {
            $salaryAdvance->fill($validated);
            $salaryAdvance->user_id = $targetUser->id;
            $salaryAdvance->save();
            if ($salaryAdvance->expense) {
                $salaryAdvance->expense->update([
                    'description' => 'Adelanto de sueldo: '.$targetUser->full_name,
                    'amount' => $salaryAdvance->amount,
                    'expense_date' => $salaryAdvance->date,
                    'notes' => $salaryAdvance->description,
                ]);
            }

            return $salaryAdvance->fresh()->load(['user', 'expense', 'originPayrollPayment']);
        });

        return response()->json($advance);
    }

    public function destroyAdvance(SalaryAdvance $salaryAdvance)
    {
        $this->ensureBusinessRecord($salaryAdvance);
        if ($salaryAdvance->status !== 'pending') {
            return response()->json(['message' => 'Solo se pueden eliminar adelantos pendientes.'], 422);
        }
        DB::transaction(function () use ($salaryAdvance) {
            $expense = $salaryAdvance->expense;
            $salaryAdvance->delete();
            $expense?->delete();
        });

        return response()->json(['message' => 'Adelanto eliminado.']);
    }

    public function calculate(Request $request, User $user)
    {
        $this->ensureBusinessUser($user);
        $request->validate(['end_date' => 'sometimes|date']);
        $businessId = $this->businessId();
        $config = UserPayrollConfig::where('user_id', $user->id)
            ->where('business_id', $businessId)
            ->first();
        if (! $config) {
            return response()->json(['message' => 'El usuario no tiene configuracion de planilla.'], 422);
        }

        $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date)->startOfDay() : now()->startOfDay();
        $lastPayment = PayrollPayment::where('user_id', $user->id)
            ->where('business_id', $businessId)
            ->latest('end_date')
            ->first();
        if ($lastPayment) {
            $startDate = Carbon::parse($lastPayment->end_date)->addDay()->startOfDay();
        } elseif ($config->start_date) {
            $startDate = Carbon::parse($config->start_date)->startOfDay();
        } else {
            $startDate = $endDate->copy();
            if ($config->payment_frequency === 'weekly') {
                $startDate->subDays(6);
            } elseif ($config->payment_frequency === 'monthly') {
                $startDate->subDays(29);
            }
        }
        if ($startDate->gt($endDate)) {
            return response()->json([
                'message' => $lastPayment
                    ? 'El periodo ya fue pagado hasta el '.$lastPayment->end_date->toDateString().'.'
                    : 'La fecha final no puede ser anterior a la fecha de inicio de planilla.',
                'last_payment_end_date' => $lastPayment?->end_date?->toDateString(),
                'payroll_start_date' => $config->start_date?->toDateString(),
            ], 422);
        }

        $advances = SalaryAdvance::where('user_id', $user->id)
            ->where('business_id', $businessId)
            ->where('status', 'pending')
            ->whereDate('date', '<=', $endDate->toDateString())
            ->orderBy('date')->orderBy('id')->get();
        $totalAdvances = round((float) $advances->sum('amount'), 2);
        $totalDaysInPeriod = (int) $startDate->diffInDays($endDate) + 1;
        $absences = AttendanceLog::where('user_id', $user->id)
            ->where('business_id', $businessId)
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->where('status', 'absent')->orderBy('date')->get();
        $absencesCount = $absences->count();
        $daysWorked = max(0, $totalDaysInPeriod - $absencesCount);
        $divisor = match ($config->payment_frequency) {
            'monthly' => 30,
            'weekly' => 7,
            default => 1,
        };
        $dailyRate = (float) $config->base_salary / $divisor;
        $grossSalary = round($daysWorked * $dailyRate, 2);
        $advancesDiscounted = round(min($grossSalary, $totalAdvances), 2);
        $carryoverAmount = round(max(0, $totalAdvances - $advancesDiscounted), 2);
        $finalPayment = round(max(0, $grossSalary - $advancesDiscounted), 2);

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->full_name],
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'total_days' => $totalDaysInPeriod,
            ],
            'config' => $config,
            'summary' => [
                'days_worked' => $daysWorked,
                'absences' => $absencesCount,
                'base_salary' => (float) $config->base_salary,
                'daily_rate' => round($dailyRate, 2),
                'gross_salary' => $grossSalary,
                'pending_advances_total' => $totalAdvances,
                'advances_to_discount' => $advancesDiscounted,
                'carryover_amount' => $carryoverAmount,
                'final_payment' => $finalPayment,
            ],
            'advances_details' => $advances,
            'absences_details' => $absences,
        ]);
    }

    public function pay(Request $request, User $user)
    {
        $this->ensureBusinessUser($user);
        $validated = $request->validate([
            'end_date' => 'required|date',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($request, $user, $validated) {
                $auth = Auth::user();
                $business = $auth->business;
                $calculationResponse = $this->calculate($request, $user);
                $calculation = $calculationResponse->getData(true);
                if ($calculationResponse->getStatusCode() !== 200 || ! isset($calculation['summary'])) {
                    return $calculationResponse;
                }
                $summary = $calculation['summary'];
                $expense = null;
                if ((float) $summary['final_payment'] > 0) {
                    $expenseCategory = Category::firstOrCreate([
                        'name' => 'Pago de Nómina',
                        'type' => 'expense',
                        'business_id' => $business->id,
                    ]);
                    $expense = Expense::create([
                        'business_id' => $business->id,
                        'description' => 'Pago de nómina: '.$user->full_name.' ('.$calculation['period']['start'].' - '.$calculation['period']['end'].')',
                        'amount' => $summary['final_payment'],
                        'expense_date' => $validated['payment_date'],
                        'category_id' => $expenseCategory->id,
                        'created_by' => $auth->id,
                        'notes' => $validated['notes'] ?? null,
                    ]);
                }

                $payment = PayrollPayment::create([
                    'user_id' => $user->id,
                    'business_id' => $business->id,
                    'expense_id' => $expense?->id,
                    'base_salary' => $summary['base_salary'],
                    'advances_discounted' => $summary['advances_to_discount'],
                    'carryover_amount' => $summary['carryover_amount'],
                    'final_payment' => $summary['final_payment'],
                    'start_date' => $calculation['period']['start'],
                    'end_date' => $calculation['period']['end'],
                    'payment_date' => $validated['payment_date'],
                    'notes' => $validated['notes'] ?? null,
                ]);

                $advanceIds = collect($calculation['advances_details'])->pluck('id');
                SalaryAdvance::where('business_id', $business->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->whereIn('id', $advanceIds)
                    ->get()
                    ->each(fn (SalaryAdvance $advance) => $advance->update([
                        'status' => 'discounted',
                        'payroll_payment_id' => $payment->id,
                    ]));

                $carryoverAdvance = null;
                if ((float) $summary['carryover_amount'] > 0) {
                    $carryoverAdvance = SalaryAdvance::create([
                        'user_id' => $user->id,
                        'business_id' => $business->id,
                        'amount' => $summary['carryover_amount'],
                        'date' => Carbon::parse($calculation['period']['end'])->addDay()->toDateString(),
                        'description' => 'Saldo restante de deuda anterior (planilla #'.$payment->id.')',
                        'status' => 'pending',
                        'origin_payroll_payment_id' => $payment->id,
                    ]);
                }

                return response()->json([
                    'message' => 'Pago de planilla registrado exitosamente.',
                    'payment' => $payment->load(['user', 'expense', 'advances', 'carryoverAdvances']),
                    'carryover_advance' => $carryoverAdvance,
                ], 201);
            });
        } catch (\Throwable $exception) {
            Log::error('Error paying payroll: '.$exception->getMessage());

            return response()->json(['message' => 'Ocurrio un error al procesar el pago.'], 500);
        }
    }

    public function getPayments(Request $request)
    {
        $request->validate([
            'user_id' => 'sometimes|integer',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);
        $query = PayrollPayment::where('business_id', $this->businessId())
            ->with(['user', 'expense', 'advances', 'carryoverAdvances']);
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('payment_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('payment_date', '<=', $request->end_date);
        }

        return response()->json($query->latest('payment_date')->latest('id')->get());
    }

    private function createAdvanceExpense(User $targetUser, float $amount, string $date, ?string $notes): Expense
    {
        $auth = Auth::user();
        $category = Category::firstOrCreate([
            'name' => 'Adelanto de Sueldo',
            'type' => 'expense',
            'business_id' => $this->businessId(),
        ]);

        return Expense::create([
            'business_id' => $this->businessId(),
            'description' => 'Adelanto de sueldo: '.$targetUser->full_name,
            'amount' => $amount,
            'expense_date' => $date,
            'category_id' => $category->id,
            'created_by' => $auth->id,
            'notes' => $notes,
        ]);
    }

    private function ensureBusinessUser(User $user): void
    {
        abort_unless((int) $user->business_id === $this->businessId(), 404);
    }

    private function ensureBusinessRecord($record): void
    {
        abort_unless((int) $record->business_id === $this->businessId(), 404);
    }

    private function businessId(): int
    {
        return (int) Auth::user()->business_id;
    }
}

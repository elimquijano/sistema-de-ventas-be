<?php

namespace Tests\Unit;

use App\Models\CashRegister;
use PHPUnit\Framework\TestCase;

class CashRegisterTest extends TestCase
{
    public function test_it_calculates_the_same_cash_to_deliver_used_by_reports_and_dashboard(): void
    {
        $cashRegister = new CashRegister([
            'initial_amount' => 100,
            'cash_sales_amount' => 250.50,
            'manual_inflow' => 30,
            'credit_collections' => 45,
        ]);

        $this->assertSame(425.50, $cashRegister->calculateCashToDeliver());
    }

    public function test_dashboard_cash_is_the_sum_of_every_cash_source(): void
    {
        $cashRegister = new CashRegister([
            'initial_amount' => 50,
            'cash_sales_amount' => 120,
            'manual_inflow' => 15,
            'credit_collections' => 25,
        ]);

        $cash = $cashRegister->calculateCashToDeliver();

        $cashRegister->cash = $cash;
        $cashRegister->report_cash_to_deliver = $cash;

        $this->assertSame(210.0, $cashRegister->cash);
        $this->assertSame($cashRegister->report_cash_to_deliver, $cashRegister->cash);
    }
}

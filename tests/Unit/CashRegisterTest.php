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
}

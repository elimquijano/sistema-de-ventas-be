<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Notifications\OrderAssignedNotification;
use Tests\TestCase;

class OrderAssignedNotificationTest extends TestCase
{
    public function test_it_builds_a_compact_push_and_a_detailed_whatsapp_message(): void
    {
        $sale = new Sale([
            'id' => 15,
            'uuid' => 'sale-uuid',
            'sale_number' => 'V-000015',
            'delivery_address' => 'Jr. San Miguel, Amarilis, Huánuco, Perú',
        ]);
        $sale->setRelation('client', new Client);
        $sale->setRelation('items', collect([
            new SaleItem(['item_name' => 'Pollo a la brasa', 'quantity' => 2]),
            new SaleItem(['item_name' => 'Gaseosa personal', 'quantity' => 1]),
        ]));

        $message = (new OrderAssignedNotification($sale))->toPreferredChannel(new User);

        $this->assertSame('Nuevo pedido', $message['title']);
        $this->assertSame(
            '2× Pollo a la brasa, 1× Gaseosa personal · Jr. San Miguel, Amarilis. Revisa la app.',
            $message['body']
        );
        $this->assertStringContainsString('• 2 × Pollo a la brasa', $message['whatsapp_body']);
        $this->assertStringContainsString("📍 *Ubicación*\nJr. San Miguel, Amarilis", $message['whatsapp_body']);
        $this->assertStringEndsWith(
            '📲 *Revisa la app* para ver todos los detalles.',
            $message['whatsapp_body']
        );
    }

    public function test_push_summarizes_large_orders_but_whatsapp_keeps_every_item(): void
    {
        $sale = new Sale(['delivery_address' => 'Av. Principal, Amarilis, Huánuco']);
        $sale->setRelation('client', new Client);
        $sale->setRelation('items', collect([
            new SaleItem(['item_name' => 'Producto uno', 'quantity' => 1]),
            new SaleItem(['item_name' => 'Producto dos', 'quantity' => 2]),
            new SaleItem(['item_name' => 'Producto tres', 'quantity' => 3]),
            new SaleItem(['item_name' => 'Producto cuatro', 'quantity' => 4]),
        ]));

        $message = (new OrderAssignedNotification($sale))->toPreferredChannel(new User);

        $this->assertStringContainsString('1× Producto uno, 2× Producto dos, +2 más', $message['body']);
        $this->assertStringNotContainsString('Producto tres', $message['body']);
        $this->assertStringContainsString('• 3 × Producto tres', $message['whatsapp_body']);
        $this->assertStringContainsString('• 4 × Producto cuatro', $message['whatsapp_body']);
    }
}

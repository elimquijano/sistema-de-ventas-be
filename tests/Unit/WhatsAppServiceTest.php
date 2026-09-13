<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\OrderNotificationFormatter;
use App\Services\WhatsAppService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    #[Test]
    public function it_formats_a_short_attention_grabbing_sale_message(): void
    {
        $sale = new Sale([
            'delivery_address' => 'jr san miguel, amarilis, Departamento de Huánuco, Perú',
        ]);
        $sale->setRelation('client', new Client([
            'address' => 'Dirección alternativa, Huánuco, Perú',
        ]));
        $sale->setRelation('items', collect([
            new SaleItem(['item_name' => 'Pollo a la brasa', 'quantity' => 2]),
            new SaleItem(['item_name' => 'Gaseosa personal', 'quantity' => 1]),
        ]));

        $message = (new WhatsAppService(new OrderNotificationFormatter))->formatSaleMessage($sale);

        $this->assertSame(
            "🚨 *NUEVO PEDIDO* 🚨\n\n"
            ."📦 *Productos*\n"
            ."• 2 × Pollo a la brasa\n"
            ."• 1 × Gaseosa personal\n\n"
            ."📍 *Ubicación*\n"
            ."jr san miguel, amarilis\n\n"
            .'📲 *Revisa la app* para ver todos los detalles.',
            $message
        );
        $this->assertStringNotContainsString('Departamento de Huánuco', $message);
        $this->assertStringNotContainsString('Perú', $message);
    }

    #[Test]
    public function it_uses_the_client_address_when_the_sale_has_no_delivery_address(): void
    {
        $sale = new Sale;
        $sale->setRelation('client', new Client([
            'address' => 'Av. Universitaria, Pillco Marca, Huánuco, Perú',
        ]));
        $sale->setRelation('items', collect([
            new SaleItem(['item_name' => 'Pizza familiar', 'quantity' => 1]),
        ]));

        $message = (new WhatsAppService(new OrderNotificationFormatter))->formatSaleMessage($sale);

        $this->assertStringContainsString(
            "📍 *Ubicación*\nAv. Universitaria, Pillco Marca",
            $message
        );
    }
}

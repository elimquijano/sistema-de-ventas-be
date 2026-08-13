<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Sale;
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

        $message = (new WhatsAppService())->formatSaleMessage($sale);

        $this->assertSame(
            "🚨 *NUEVO PEDIDO* 🚨\n\n"
            . "📍 Tienes un pedido para *jr san miguel, amarilis*.\n\n"
            . "📲 Revisa el aplicativo para ver todos los detalles.",
            $message
        );
        $this->assertStringNotContainsString('Departamento de Huánuco', $message);
        $this->assertStringNotContainsString('Perú', $message);
    }

    #[Test]
    public function it_uses_the_client_address_when_the_sale_has_no_delivery_address(): void
    {
        $sale = new Sale();
        $sale->setRelation('client', new Client([
            'address' => 'Av. Universitaria, Pillco Marca, Huánuco, Perú',
        ]));

        $message = (new WhatsAppService())->formatSaleMessage($sale);

        $this->assertStringContainsString(
            'Tienes un pedido para *Av. Universitaria, Pillco Marca*.',
            $message
        );
    }
}

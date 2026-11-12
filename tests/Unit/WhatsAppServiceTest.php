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
    public function test_order_request_is_written_without_waiting_for_an_http_response(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($server);

        $url = 'http://'.stream_socket_get_name($server, false).'/api/v1/messages/text';
        $previousUrl = getenv('WHATSAPP_API_URL');
        $previousToken = getenv('WHATSAPP_API_TOKEN');
        $previousEnvUrl = $_ENV['WHATSAPP_API_URL'] ?? null;
        $previousEnvToken = $_ENV['WHATSAPP_API_TOKEN'] ?? null;
        $previousServerUrl = $_SERVER['WHATSAPP_API_URL'] ?? null;
        $previousServerToken = $_SERVER['WHATSAPP_API_TOKEN'] ?? null;

        putenv('WHATSAPP_API_URL='.$url);
        putenv('WHATSAPP_API_TOKEN=test-token');
        $_ENV['WHATSAPP_API_URL'] = $_SERVER['WHATSAPP_API_URL'] = $url;
        $_ENV['WHATSAPP_API_TOKEN'] = $_SERVER['WHATSAPP_API_TOKEN'] = 'test-token';

        try {
            $service = new WhatsAppService(new OrderNotificationFormatter);
            $this->assertTrue($service->sendMessageWithoutWaiting('999888777', 'Nuevo pedido'));

            $client = stream_socket_accept($server, 1);
            $this->assertNotFalse($client);
            $request = stream_get_contents($client);

            $this->assertStringContainsString('POST /api/v1/messages/text HTTP/1.1', $request);
            $this->assertStringContainsString('Authorization: Bearer test-token', $request);
            $this->assertStringContainsString('"recipient":"51999888777"', $request);
            $this->assertStringContainsString('"body":"Nuevo pedido"', $request);
            fclose($client);
        } finally {
            fclose($server);
            $previousUrl === false ? putenv('WHATSAPP_API_URL') : putenv('WHATSAPP_API_URL='.$previousUrl);
            $previousToken === false ? putenv('WHATSAPP_API_TOKEN') : putenv('WHATSAPP_API_TOKEN='.$previousToken);
            $this->restoreEnvironmentValue('WHATSAPP_API_URL', $previousEnvUrl, $previousServerUrl);
            $this->restoreEnvironmentValue('WHATSAPP_API_TOKEN', $previousEnvToken, $previousServerToken);
        }
    }

    private function restoreEnvironmentValue(string $key, ?string $envValue, ?string $serverValue): void
    {
        if ($envValue === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $envValue;
        }

        if ($serverValue === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $serverValue;
        }
    }

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

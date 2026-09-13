<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrderNotificationFormatter
{
    public function pushBody(Sale $sale): string
    {
        $sale->loadMissing(['items', 'client']);

        $segments = array_filter([
            $this->compactItems($sale->items),
            Str::limit($this->shortAddress($sale), 60, '…'),
        ]);

        $summary = $segments !== []
            ? implode(' · ', $segments)
            : 'Tienes un pedido asignado';

        return $summary.'. Revisa la app.';
    }

    public function whatsAppBody(Sale $sale): string
    {
        $sale->loadMissing(['items', 'client']);

        $items = $sale->items->isNotEmpty()
            ? $sale->items
                ->map(fn ($item): string => "• {$this->quantity($item->quantity)} × {$item->item_name}")
                ->implode("\n")
            : '• Sin detalle de productos';

        $address = $this->shortAddress($sale) ?: 'Ubicación no registrada';

        return "🚨 *NUEVO PEDIDO* 🚨\n\n"
            ."📦 *Productos*\n{$items}\n\n"
            ."📍 *Ubicación*\n{$address}\n\n"
            .'📲 *Revisa la app* para ver todos los detalles.';
    }

    /** @param Collection<int, mixed> $items */
    private function compactItems(Collection $items): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        $visibleItems = $items
            ->take(2)
            ->map(fn ($item): string => "{$this->quantity($item->quantity)}× ".Str::limit((string) $item->item_name, 32, '…'))
            ->values();

        if ($items->count() > 2) {
            $visibleItems->push('+'.($items->count() - 2).' más');
        }

        return $visibleItems->implode(', ');
    }

    private function shortAddress(Sale $sale): string
    {
        $address = $sale->delivery_address ?: $sale->client?->address;
        $parts = array_values(array_filter(
            array_map('trim', explode(',', (string) $address)),
            fn (string $part): bool => $part !== ''
        ));

        return implode(', ', array_slice($parts, 0, 2));
    }

    private function quantity(mixed $quantity): string
    {
        $numericQuantity = (float) $quantity;

        return floor($numericQuantity) === $numericQuantity
            ? (string) (int) $numericQuantity
            : rtrim(rtrim(number_format($numericQuantity, 2, '.', ''), '0'), '.');
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ShippingSettings
{
    /**
     * @return array{min_order_price: float, shipping_charge: float, enabled: bool}
     */
    public static function all(): array
    {
        $defaults = self::defaults();
        $path = config('shipping_settings.file', 'shipping_settings.json');

        if (!Storage::disk('local')->exists($path)) {
            return $defaults;
        }

        $decoded = json_decode(Storage::disk('local')->get($path), true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        return [
            'min_order_price' => self::toMoney($decoded['min_order_price'] ?? $defaults['min_order_price']),
            'shipping_charge' => self::toMoney($decoded['shipping_charge'] ?? $defaults['shipping_charge']),
            'enabled' => self::toBool($decoded['enabled'] ?? $defaults['enabled']),
        ];
    }

    /**
     * @param  array{min_order_price?: mixed, shipping_charge?: mixed, enabled?: mixed}  $input
     * @return array{min_order_price: float, shipping_charge: float, enabled: bool}
     */
    public static function save(array $input): array
    {
        $current = self::all();

        $next = [
            'min_order_price' => array_key_exists('min_order_price', $input)
                ? self::toMoney($input['min_order_price'])
                : $current['min_order_price'],
            'shipping_charge' => array_key_exists('shipping_charge', $input)
                ? self::toMoney($input['shipping_charge'])
                : $current['shipping_charge'],
            'enabled' => array_key_exists('enabled', $input)
                ? self::toBool($input['enabled'])
                : $current['enabled'],
        ];

        if ($next['min_order_price'] < 0) {
            $next['min_order_price'] = 0;
        }

        if ($next['shipping_charge'] < 0) {
            $next['shipping_charge'] = 0;
        }

        Storage::disk('local')->put(
            config('shipping_settings.file', 'shipping_settings.json'),
            json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $next;
    }

    public static function chargeForSubtotal(float $subtotal): float
    {
        $settings = self::all();

        if (!$settings['enabled']) {
            return 0.0;
        }

        if ($subtotal <= 0) {
            return 0.0;
        }

        if ($subtotal >= $settings['min_order_price']) {
            return 0.0;
        }

        return $settings['shipping_charge'];
    }

    /**
     * @return array{min_order_price: float, shipping_charge: float, enabled: bool}
     */
    private static function defaults(): array
    {
        $fromFile = config('shipping_settings.defaults', []);
        $legacyMin = config('liwaas.min_order_price', 500);
        $legacyFee = config('liwaas.shipping_charge', 80);

        return [
            'min_order_price' => self::toMoney($fromFile['min_order_price'] ?? $legacyMin),
            'shipping_charge' => self::toMoney($fromFile['shipping_charge'] ?? $legacyFee),
            'enabled' => self::toBool($fromFile['enabled'] ?? true),
        ];
    }

    private static function toMoney(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }
}

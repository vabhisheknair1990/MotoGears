<?php

namespace App\Services;

use App\Exceptions\BusinessException;

class ShippingService
{
    public function __construct(private SettingsService $settings) {}

    public function methodCodes(): array
    {
        return ['standard', 'express'];
    }

    /**
     * @param  float  $netMerchandise  subtotal after discount
     */
    public function cost(string $method, float $netMerchandise, bool $freeShippingCoupon = false): float
    {
        if (! in_array($method, $this->methodCodes(), true)) {
            throw new BusinessException('Unsupported shipping method.', 422, ['shipping_method' => ['Unsupported shipping method.']]);
        }
        if ($netMerchandise <= 0) {
            return 0.0;
        }
        if ($freeShippingCoupon) {
            return 0.0;
        }

        return match ($method) {
            'standard' => $netMerchandise >= $this->settings->float('free_shipping_threshold') ? 0.0 : $this->settings->float('standard_shipping_cost'),
            'express' => $this->settings->float('express_shipping_cost'),
        };
    }

    public function options(float $netMerchandise, bool $freeShippingCoupon = false): array
    {
        return [
            [
                'code' => 'standard',
                'label' => 'Standard Delivery',
                'description' => '4–6 business days. Free above ₹'.number_format($this->settings->float('free_shipping_threshold')).'.',
                'eta_days' => [4, 6],
                'cost' => $this->cost('standard', $netMerchandise, $freeShippingCoupon),
            ],
            [
                'code' => 'express',
                'label' => 'Express Delivery',
                'description' => '1–2 business days in metro cities.',
                'eta_days' => [1, 2],
                'cost' => $this->cost('express', $netMerchandise, $freeShippingCoupon),
            ],
        ];
    }
}

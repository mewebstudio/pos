<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;

class PosNetV1PosResponseValueMapper extends AbstractResponseValueMapper
{
    /**
     * @var array<int, PosInterface::CURRENCY_*>
     */
    private array $orderStatusCurrencyMappings = [
        '949' => PosInterface::CURRENCY_TRY,
        '840' => PosInterface::CURRENCY_USD,
        '978' => PosInterface::CURRENCY_EUR,
        '826' => PosInterface::CURRENCY_GBP,
        '392' => PosInterface::CURRENCY_JPY,
        '643' => PosInterface::CURRENCY_RUB,
    ];

    /**
     * @inheritDoc
     */
    protected array $orderStatusMappings = [
        PosInterface::TX_TYPE_PAY_AUTH => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        PosInterface::TX_TYPE_CANCEL   => PosInterface::PAYMENT_STATUS_CANCELED,
        PosInterface::TX_TYPE_REFUND   => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNetV1Pos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapCurrency($currency, string $apiRequestTxType = null): ?string
    {
        if (PosInterface::TX_TYPE_STATUS !== $apiRequestTxType) {
            return $this->orderStatusCurrencyMappings[$currency] ?? null;
        }

        return parent::mapCurrency($currency, $apiRequestTxType);
    }
}

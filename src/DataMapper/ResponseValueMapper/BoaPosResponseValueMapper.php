<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\PosInterface;

/**
 * Value mapper for Boa Gateways such as KuveytPos and VakifKatilimPos
 */
class BoaPosResponseValueMapper extends AbstractResponseValueMapper
{
    /**
     * Order Status Codes
     *
     * @inheritDoc
     */
    protected array $orderStatusMappings = [
        1 => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        4 => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
        5 => PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        6 => PosInterface::PAYMENT_STATUS_CANCELED,
    ];

    /**
     * in '0949' or '949' formats
     * @inheritDoc
     */
    public function mapCurrency($currency, string $apiRequestTxType = null): ?string
    {
        // 949 => 0949; for the request gateway wants 0949 code, but in response they send 949 code.
        $currencyNormalized = \str_pad((string) $currency, 4, '0', STR_PAD_LEFT);

        return parent::mapCurrency($currencyNormalized, $apiRequestTxType);
    }
}

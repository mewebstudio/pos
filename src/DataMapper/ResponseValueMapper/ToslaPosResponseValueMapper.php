<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\PosInterface;

class ToslaPosResponseValueMapper extends AbstractResponseValueMapper
{
    /**
     * @inheritdoc
     */
    protected array $orderStatusMappings = [
        0 => PosInterface::PAYMENT_STATUS_ERROR,
        1 => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        2 => PosInterface::PAYMENT_STATUS_CANCELED,
        3 => PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        4 => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
        5 => PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED,
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ToslaPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapTxType($txType, ?string $paymentModel = null): ?string
    {
        if (0 === $txType) {
            return null;
        }

        return parent::mapTxType((string) $txType);
    }
}

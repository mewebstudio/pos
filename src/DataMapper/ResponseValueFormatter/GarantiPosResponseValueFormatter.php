<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;

class GarantiPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return GarantiPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function formatInstallment(?string $installment, string $txType): int
    {
        if (null === $installment) {
            return 0;
        }

        // history response
        if (PosInterface::TX_TYPE_HISTORY === $txType && ('Pesin' === $installment || '1' === $installment)) {
            return 0;
        }

        return parent::formatInstallment($installment, $txType);
    }

    /**
     * 100001 => 1000.01
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        return ((float) $amount) / 100;
    }
}

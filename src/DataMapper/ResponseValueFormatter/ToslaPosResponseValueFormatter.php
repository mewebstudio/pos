<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\ToslaPos;

class ToslaPosResponseValueFormatter extends AbstractResponseValueFormatter
{
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
    public function formatInstallment(?string $installment, string $txType): int
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        return ((float) $amount) / 100;
    }
}

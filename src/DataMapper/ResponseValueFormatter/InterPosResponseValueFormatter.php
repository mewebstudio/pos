<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueFormatter;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\InterPos;

class InterPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return InterPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function formatInstallment(?string $installment, string $txType): int
    {
        throw new NotImplementedException();
    }

    /**
     * 0 => 0.0
     * 1.056,2 => 1056.2
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        return (float) \str_replace(',', '.', \str_replace('.', '', (string) $amount));
    }
}

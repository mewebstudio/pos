<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\PosInterface;

class ToslaPosRequestValueMapper extends AbstractRequestValueMapper
{
    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => '1',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => '2',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => '3',
        PosInterface::TX_TYPE_CANCEL         => '4',
        PosInterface::TX_TYPE_REFUND         => '5',
        PosInterface::TX_TYPE_REFUND_PARTIAL => '5',
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
     *
     * @return int
     */
    public function mapCurrency(string $currency): int
    {
        return (int) $this->currencyMappings[$currency];
    }
}

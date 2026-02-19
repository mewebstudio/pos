<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Gateways\PosNet;
use Mews\Pos\PosInterface;

class PosNetRequestValueMapper extends AbstractRequestValueMapper
{
    /**
     * {@inheritDoc}
     */
    protected array $langMappings = [
        PosInterface::LANG_TR => 'tr',
        PosInterface::LANG_EN => 'en',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'Sale',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'Auth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'Capt',
        PosInterface::TX_TYPE_CANCEL         => 'reverse',
        PosInterface::TX_TYPE_REFUND         => 'return',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'return',
        PosInterface::TX_TYPE_STATUS         => 'agreement',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => 'TL',
        PosInterface::CURRENCY_USD => 'US',
        PosInterface::CURRENCY_EUR => 'EU',
        PosInterface::CURRENCY_GBP => 'GB',
        PosInterface::CURRENCY_JPY => 'JP',
        PosInterface::CURRENCY_RUB => 'RU',
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNet::class === $gatewayClass;
    }
}

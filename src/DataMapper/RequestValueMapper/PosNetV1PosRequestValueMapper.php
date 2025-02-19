<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\PosInterface;

class PosNetV1PosRequestValueMapper extends AbstractRequestValueMapper
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
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'Capture',
        PosInterface::TX_TYPE_CANCEL         => 'Reverse',
        PosInterface::TX_TYPE_REFUND         => 'Return',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'Return',
        PosInterface::TX_TYPE_STATUS         => 'TransactionInquiry',
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
}

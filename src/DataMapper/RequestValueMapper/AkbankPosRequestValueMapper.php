<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\PosInterface;

class AkbankPosRequestValueMapper extends AbstractRequestValueMapper
{
    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_NON_SECURE => '1000',
            PosInterface::MODEL_3D_SECURE  => '3000',
            PosInterface::MODEL_3D_PAY     => '3000',
            PosInterface::MODEL_3D_HOST    => '3000',
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => [
            PosInterface::MODEL_NON_SECURE => '1004',
            PosInterface::MODEL_3D_SECURE  => '3004',
            PosInterface::MODEL_3D_PAY     => '3004',
            PosInterface::MODEL_3D_HOST    => '3004',
        ],
        PosInterface::TX_TYPE_PAY_POST_AUTH  => '1005',
        PosInterface::TX_TYPE_REFUND         => '1002',
        PosInterface::TX_TYPE_REFUND_PARTIAL => '1002',
        PosInterface::TX_TYPE_CANCEL         => '1003',
        PosInterface::TX_TYPE_ORDER_HISTORY  => '1010',
        PosInterface::TX_TYPE_HISTORY        => '1009',
    ];

    /**
     * @var non-empty-array<PosInterface::CURRENCY_*, int>
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => 949,
        PosInterface::CURRENCY_USD => 840,
        PosInterface::CURRENCY_EUR => 978,
        PosInterface::CURRENCY_GBP => 826,
        PosInterface::CURRENCY_JPY => 392,
        PosInterface::CURRENCY_RUB => 643,
    ];

    /**
     * {@inheritdoc}
     */
    protected array $recurringOrderFrequencyMappings = [
        'DAY'   => 'D',
        'WEEK'  => 'W',
        'MONTH' => 'M',
        'YEAR'  => 'Y',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE  => '3D',
        PosInterface::MODEL_3D_PAY     => '3D_PAY',
        PosInterface::MODEL_3D_HOST    => '3D_PAY_HOSTING',
        PosInterface::MODEL_NON_SECURE => 'PAY_HOSTING',
    ];

    /** @var array<PosInterface::LANG_*, string> */
    protected array $langMappings = [
        PosInterface::LANG_TR => 'TR',
        PosInterface::LANG_EN => 'EN',
    ];
}

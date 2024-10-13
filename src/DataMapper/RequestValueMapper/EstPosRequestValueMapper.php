<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\PosInterface;

class EstPosRequestValueMapper extends AbstractRequestValueMapper
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
        PosInterface::TX_TYPE_PAY_AUTH       => 'Auth',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'PreAuth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'PostAuth',
        PosInterface::TX_TYPE_CANCEL         => 'Void',
        PosInterface::TX_TYPE_REFUND         => 'Credit',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'Credit',
        PosInterface::TX_TYPE_STATUS         => 'ORDERSTATUS',
        PosInterface::TX_TYPE_HISTORY        => 'ORDERHISTORY',
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
        PosInterface::MODEL_3D_SECURE      => '3d',
        PosInterface::MODEL_3D_PAY         => '3d_pay',
        PosInterface::MODEL_3D_PAY_HOSTING => '3d_pay_hosting',
        PosInterface::MODEL_3D_HOST        => '3d_host',
        PosInterface::MODEL_NON_SECURE     => 'regular',
    ];

    /**
     * @inheritDoc
     *
     * @return string
     */
    public function mapCurrency(string $currency): string
    {
        return (string) $this->currencyMappings[$currency];
    }
}

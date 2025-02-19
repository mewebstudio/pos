<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\PosInterface;

class PayForPosRequestValueMapper extends AbstractRequestValueMapper
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
        PosInterface::TX_TYPE_REFUND         => 'Refund',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'Refund',
        PosInterface::TX_TYPE_HISTORY        => 'TxnHistory',
        PosInterface::TX_TYPE_STATUS         => 'OrderInquiry',
    ];

    /** {@inheritdoc} */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE  => '3DModel',
        PosInterface::MODEL_3D_PAY     => '3DPay',
        PosInterface::MODEL_3D_HOST    => '3DHost',
        PosInterface::MODEL_NON_SECURE => 'NonSecure',
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

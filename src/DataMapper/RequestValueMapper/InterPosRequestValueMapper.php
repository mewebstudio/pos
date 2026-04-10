<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\PosInterface;

class InterPosRequestValueMapper extends AbstractRequestValueMapper
{
    /**
     * {@inheritDoc}
     */
    protected array $langMappings = [
        PosInterface::LANG_TR => 'tr',
        PosInterface::LANG_EN => 'en',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $cardTypeMappings = [
        CreditCardInterface::CARD_TYPE_VISA       => '0',
        CreditCardInterface::CARD_TYPE_MASTERCARD => '1',
        CreditCardInterface::CARD_TYPE_AMEX       => '2',
        CreditCardInterface::CARD_TYPE_TROY       => '3',
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
        PosInterface::TX_TYPE_STATUS         => 'StatusHistory',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE  => '3DModel',
        PosInterface::MODEL_3D_PAY     => '3DPay',
        PosInterface::MODEL_3D_HOST    => '3DHost',
        PosInterface::MODEL_NON_SECURE => 'NonSecure',
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return InterPos::class === $gatewayClass;
    }

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

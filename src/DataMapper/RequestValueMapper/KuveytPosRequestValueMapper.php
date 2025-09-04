<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\PosInterface;

class KuveytPosRequestValueMapper extends AbstractRequestValueMapper
{
    /**
     * Currency mapping
     *
     * {@inheritdoc}
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => '0949',
        PosInterface::CURRENCY_USD => '0840',
        PosInterface::CURRENCY_EUR => '0978',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $cardTypeMappings = [
        CreditCardInterface::CARD_TYPE_VISA       => 'Visa',
        CreditCardInterface::CARD_TYPE_MASTERCARD => 'MasterCard',
        CreditCardInterface::CARD_TYPE_TROY       => 'Troy',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'Sale',
        PosInterface::TX_TYPE_CANCEL         => 'SaleReversal',
        PosInterface::TX_TYPE_STATUS         => 'GetMerchantOrderDetail',
        PosInterface::TX_TYPE_REFUND         => 'Drawback',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'PartialDrawback',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE  => '3',
        PosInterface::MODEL_NON_SECURE => '0',
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return KuveytPos::class === $gatewayClass
            || KuveytSoapApiPos::class === $gatewayClass;
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

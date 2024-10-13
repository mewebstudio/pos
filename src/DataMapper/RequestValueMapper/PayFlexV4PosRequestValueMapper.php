<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\PosInterface;

class PayFlexV4PosRequestValueMapper extends AbstractRequestValueMapper
{
    /**
     * {@inheritdoc}
     */
    protected array $cardTypeMappings = [
        CreditCardInterface::CARD_TYPE_VISA       => '100',
        CreditCardInterface::CARD_TYPE_MASTERCARD => '200',
        CreditCardInterface::CARD_TYPE_TROY       => '300',
        CreditCardInterface::CARD_TYPE_AMEX       => '400',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'Sale',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'Auth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'Capture',
        PosInterface::TX_TYPE_CANCEL         => 'Cancel',
        PosInterface::TX_TYPE_REFUND         => 'Refund',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'Refund',
        PosInterface::TX_TYPE_STATUS         => 'status',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $recurringOrderFrequencyMappings = [
        'DAY'   => 'Day',
        'MONTH' => 'Month',
        'YEAR'  => 'Year',
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

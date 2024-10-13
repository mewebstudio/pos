<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\PosInterface;

class PayFlexCPV4PosRequestValueMapper extends AbstractRequestValueMapper
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
        PosInterface::TX_TYPE_HISTORY        => 'TxnHistory',
        PosInterface::TX_TYPE_STATUS         => 'OrderInquiry',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $langMappings = [
        PosInterface::LANG_TR => 'tr-TR',
        PosInterface::LANG_EN => 'en-US',
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

<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\PosInterface;

class ParamPosResponseValueMapper extends AbstractResponseValueMapper
{
    /** @var array<string, PosInterface::CURRENCY_*> */
    protected array $currencyMappings = [
        'TRL' => PosInterface::CURRENCY_TRY,
        'TL'  => PosInterface::CURRENCY_TRY,
        'EUR' => PosInterface::CURRENCY_EUR,
        'USD' => PosInterface::CURRENCY_USD,
    ];

    /** @var array<string|int, PosInterface::MODEL_*> */
    protected array $secureTypeMappings = [
        'NONSECURE' => PosInterface::MODEL_NON_SECURE,
        '3D'        => PosInterface::MODEL_3D_SECURE,
    ];

    /**
     * @var array<string, PosInterface::PAYMENT_STATUS_*>
     */
    protected array $orderStatusMappings = [
        'FAIL'           => PosInterface::PAYMENT_STATUS_ERROR,
        'BANK_FAIL'      => PosInterface::PAYMENT_STATUS_ERROR,
        'SUCCESS'        => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'CANCEL'         => PosInterface::PAYMENT_STATUS_CANCELED,
        'REFUND'         => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
        'PARTIAL_REFUND' => PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED,
    ];

    /**
     * @var array<string, PosInterface::TX_TYPE_*>
     */
    private array $statusRequestTxMappings = [
        'SALE'      => PosInterface::TX_TYPE_PAY_AUTH,
        'PRE_AUTH'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
        'POST_AUTH' => PosInterface::TX_TYPE_PAY_POST_AUTH,
    ];

    /**
     * @var array<string, PosInterface::TX_TYPE_*>
     */
    private array $historyRequestTxMappings = [
        'Satış' => PosInterface::TX_TYPE_PAY_AUTH,
        'İptal' => PosInterface::TX_TYPE_CANCEL,
        'İade'  => PosInterface::TX_TYPE_REFUND,
    ];

    /**
     * @inheritDoc
     */
    public function mapTxType($txType, ?string $paymentModel = null): ?string
    {
        return $this->statusRequestTxMappings[$txType]
            ?? $this->historyRequestTxMappings[$txType]
            ?? parent::mapTxType($txType);
    }
}

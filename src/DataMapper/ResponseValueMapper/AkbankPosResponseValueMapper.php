<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\PosInterface;

class AkbankPosResponseValueMapper extends AbstractResponseValueMapper
{
    /**
     * N: Normal
     * S: Şüpheli
     * V: İptal
     * R: Reversal
     * @var array<string, PosInterface::PAYMENT_STATUS_*>
     */
    protected array $orderStatusMappings = [
        'N'         => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'S'         => PosInterface::PAYMENT_STATUS_ERROR,
        'V'         => PosInterface::PAYMENT_STATUS_CANCELED,
        'R'         => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,

        // status that are return on history request
        'Başarılı'  => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'Başarısız' => PosInterface::PAYMENT_STATUS_ERROR,
        'İptal'     => PosInterface::PAYMENT_STATUS_CANCELED,
    ];

    /**
     * @var array<string, PosInterface::PAYMENT_STATUS_*>
     */
    private array $recurringOrderStatusMappings = [
        'S' => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'W' => PosInterface::PAYMENT_STATUS_PAYMENT_PENDING,
        // when fulfilled payment is canceled
        'V' => PosInterface::PAYMENT_STATUS_CANCELED,
        // when unfulfilled payment is canceled
        'C' => PosInterface::PAYMENT_STATUS_CANCELED,
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AkbankPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapOrderStatus($orderStatus, ?string $preAuthStatus = null, bool $isRecurringOrder = false)
    {
        if ($isRecurringOrder) {
            return $this->recurringOrderStatusMappings[$orderStatus] ?? $orderStatus;
        }

        $mappedOrderStatus = $this->orderStatusMappings[$orderStatus] ?? $orderStatus;
        /**
         * preAuthStatus
         * "O": Açık
         * "C": Kapalı
         */
        if (PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $mappedOrderStatus && 'O' === $preAuthStatus) {
            return PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED;
        }

        return $mappedOrderStatus;
    }
}

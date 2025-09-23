<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\PosInterface;

class EstPosResponseValueMapper extends AbstractResponseValueMapper
{
    /**
     * @var array<string, PosInterface::TX_TYPE_*>
     */
    private array $historyResponseTxTypeMappings = [
        /**
         * S: Auth/PreAuth/PostAuth
         * C: Refund
         */
        'S' => PosInterface::TX_TYPE_PAY_AUTH,
        'C' => PosInterface::TX_TYPE_REFUND,
    ];

    /**
     * D : Başarısız işlem
     * A : Otorizasyon, gün sonu kapanmadan
     * C : Ön otorizasyon kapama, gün sonu kapanmadan
     * PN : Bekleyen İşlem
     * CNCL : İptal Edilmiş İşlem
     * ERR : Hata Almış İşlem
     * S : Satış
     * R : Teknik İptal gerekiyor
     * V : İptal
     * @inheritdoc
     */
    protected array $orderStatusMappings = [
        'D'    => PosInterface::PAYMENT_STATUS_ERROR,
        'ERR'  => PosInterface::PAYMENT_STATUS_ERROR,
        'A'    => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'C'    => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'S'    => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'PN'   => PosInterface::PAYMENT_STATUS_PAYMENT_PENDING,
        'CNCL' => PosInterface::PAYMENT_STATUS_CANCELED,
        'V'    => PosInterface::PAYMENT_STATUS_CANCELED,
    ];

    /**
     * @inheritDoc
     */
    public function mapTxType($txType, ?string $paymentModel = null): ?string
    {
        return $this->historyResponseTxTypeMappings[$txType] ?? null;
    }
}

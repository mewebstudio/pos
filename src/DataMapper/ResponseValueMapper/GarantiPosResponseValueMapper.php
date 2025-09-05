<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;

class GarantiPosResponseValueMapper extends AbstractResponseValueMapper
{
    /**
     * @var array<string, PosInterface::CURRENCY_*>
     */
    private array $historyResponseCurrencyMapping = [
        'TL'  => PosInterface::CURRENCY_TRY,
        'USD' => PosInterface::CURRENCY_USD,
        'EUR' => PosInterface::CURRENCY_EUR,
        'RUB' => PosInterface::CURRENCY_RUB,
        'JPY' => PosInterface::CURRENCY_JPY,
        'GBP' => PosInterface::CURRENCY_GBP,
    ];

    /**
     * @var array<string, PosInterface::TX_TYPE_*>
     */
    private array $historyResponseTxTypes = [
        'Satis'                 => PosInterface::TX_TYPE_PAY_AUTH,
        'On Otorizasyon'        => PosInterface::TX_TYPE_PAY_PRE_AUTH,
        'On Otorizasyon Kapama' => PosInterface::TX_TYPE_PAY_POST_AUTH,
        'Iade'                  => PosInterface::TX_TYPE_REFUND,
        'Iptal'                 => PosInterface::TX_TYPE_CANCEL,
        // ... Odul Sorgulama
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return GarantiPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapTxType($txType, ?string $paymentModel = null): ?string
    {
        return $this->historyResponseTxTypes[$txType] ?? parent::mapTxType($txType, $paymentModel);
    }

    /**
     * @inheritDoc
     */
    public function mapSecureType(string $securityType, string $apiRequestTxType = null): ?string
    {
        if (PosInterface::TX_TYPE_HISTORY === $apiRequestTxType) {
            // mappings for the field SafeType of history response
            // 3D Secure => 3D
            // 3D Pay => 3D
            // NonSecure => ''
            if ('3D' === $securityType) {
                return PosInterface::MODEL_3D_SECURE;
            }

            if ('' === $securityType) {
                return PosInterface::MODEL_NON_SECURE;
            }

            return null;
        }

        return parent::mapSecureType($securityType, $apiRequestTxType);
    }

    /**
     * @inheritDoc
     */
    public function mapCurrency($currency, string $apiRequestTxType = null): ?string
    {
        if (PosInterface::TX_TYPE_HISTORY === $apiRequestTxType) {
            return $this->historyResponseCurrencyMapping[$currency] ?? null;
        }

        return parent::mapCurrency($currency, $apiRequestTxType);
    }

    /**
     * @param PosInterface::TX_TYPE_*|null $requestTxType request transaction type
     * @param PosInterface::TX_TYPE_*|null $txType txType of the transaction that is being processed
     *
     * @inheritDoc
     */
    public function mapOrderStatus($orderStatus, string $requestTxType = null, string $txType = null)
    {
        if (PosInterface::TX_TYPE_STATUS === $requestTxType) {
            // todo ChargeType degere gore belki daha duzgun mapping edebiliriz.
            //  ChargeType hakkinda dokumantasyonda sadece su bilgi var:
            //  "İşlem tipinin gösterildiği alandır."
            //  Alabilicegi degerleri hakkinda bir bilgi bulunmamakta.
            if ('WAITINGPOSTAUTH' === $orderStatus) {
                return PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED;
            }
            if ('APPROVED' === $orderStatus) {
                // how can we map this status?
                return $orderStatus;
            }

            return $orderStatus;
        }

        if (PosInterface::TX_TYPE_HISTORY === $requestTxType) {
            if (null === $txType) {
                return $orderStatus;
            }

            // $orderStatus possible values:
            // Basarili
            // Basarisiz
            // Iptal
            // Onaylandi

            if ('Basarili' === $orderStatus || 'Onaylandi' === $orderStatus) {
                if (PosInterface::TX_TYPE_CANCEL === $txType) {
                    return PosInterface::PAYMENT_STATUS_CANCELED;
                }

                if (PosInterface::TX_TYPE_REFUND === $txType) {
                    // todo how can we decide if order is partially or fully refunded?
                    return PosInterface::PAYMENT_STATUS_FULLY_REFUNDED;
                }

                if (PosInterface::TX_TYPE_PAY_AUTH === $txType || PosInterface::TX_TYPE_PAY_POST_AUTH === $txType) {
                    return PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED;
                }

                if (PosInterface::TX_TYPE_PAY_PRE_AUTH === $txType) {
                    return PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED;
                }

                return $orderStatus;
            }

            if ('Iptal' === $orderStatus) {
                /**
                 *  todo anlasilmayan durumlar:
                 *   - "Status" => "Iptal", "TrxType" => "Satis"
                 *   - "Status" => "Iptal", "TrxType" => "On Otorizasyon"
                 *   - "Status" => "Iptal", "TrxType" => "Iptal"
                 *   - "Status" => "Iptal", "TrxType" => "Iade"
                 */
                return $orderStatus;
            }

            return PosInterface::PAYMENT_STATUS_ERROR;
        }

        return $orderStatus;
    }
}

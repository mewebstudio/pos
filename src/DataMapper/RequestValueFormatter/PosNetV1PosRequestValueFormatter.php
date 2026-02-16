<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueFormatter;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;

class PosNetV1PosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNetV1Pos::class === $gatewayClass;
    }

    /**
     * PosNet requires order id with specific length
     * @var int
     */
    private const ORDER_ID_LENGTH = 20;

    /**
     * order id total length including prefix;
     * @var int
     */
    private const ORDER_ID_TOTAL_LENGTH = 24;

    /** @var string */
    private const ORDER_ID_3D_PREFIX = 'TDS_';

    /** @var string */
    private const ORDER_ID_3D_PAY_PREFIX = ''; //?

    /** @var string */
    private const ORDER_ID_REGULAR_PREFIX = ''; //?


    /**
     * 0 => '0'
     * 1 => '0'
     * 2 => '2'
     *
     * @inheritDoc
     */
    public function formatInstallment(int $installment): string
    {
        if ($installment > 1) {
            return (string) $installment;
        }

        return '0';
    }

    /**
     * example: 10.01 to 1001
     *
     * @inheritDoc
     */
    public function formatAmount(float $amount, ?string $txType = null): int
    {
        return (int) (\round($amount, 2) * 100);
    }

    /**
     * @inheritDoc
     */
    public function formatCardExpDate(\DateTimeInterface $expDate, string $fieldName): string
    {
        return $expDate->format('ym');
    }

    /**
     * @inheritDoc
     */
    public function formatDateTime(\DateTimeInterface $dateTime, ?string $fieldName = null): string
    {
        throw new NotImplementedException();
    }

    /**
     * formats order id by adding 0 pad to the left and adding prefix
     *
     * @param string                       $orderId
     * @param PosInterface::TX_TYPE_*|null $txType
     * @param PosInterface::MODEL_*|null   $orderPaymentModel payment model of the order
     *
     * @return string
     */
    public function formatOrderId(string $orderId, ?string $txType = null, ?string $orderPaymentModel = null): string
    {
        $prefix    = '';
        $padLength = self::ORDER_ID_LENGTH;

        if (\in_array($txType, [
            PosInterface::TX_TYPE_STATUS,
            PosInterface::TX_TYPE_REFUND,
            PosInterface::TX_TYPE_REFUND_PARTIAL,
            PosInterface::TX_TYPE_CANCEL,
        ], true)) {
            /**
             *  To check the status of an order or cancel/refund order Yapikredi
             *  - requires the order length to be 24
             *  - and order id prefix which is "TDSC" for 3D payments
             */
            $prefix    = self::ORDER_ID_REGULAR_PREFIX;
            $padLength = self::ORDER_ID_TOTAL_LENGTH;
            if (PosInterface::MODEL_3D_SECURE === $orderPaymentModel) {
                $prefix = self::ORDER_ID_3D_PREFIX;
            } elseif (PosInterface::MODEL_3D_PAY === $orderPaymentModel) {
                $prefix = self::ORDER_ID_3D_PAY_PREFIX;

            }
        }

        if (\strlen($orderId) > $padLength) {
            throw new \InvalidArgumentException(\sprintf(
                // Banka tarafindan belirlenen kisitlama
                "Saglanan siparis ID'nin (%s) uzunlugu %d karakter. Siparis ID %d karakterden uzun olamaz!",
                $orderId,
                \strlen($orderId),
                $padLength
            ));
        }

        return $prefix.\str_pad($orderId, $padLength - strlen($prefix), '0', STR_PAD_LEFT);
    }
}

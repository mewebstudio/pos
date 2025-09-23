<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\PosInterface;

/**
 * Maps order/request values to values that are expected by the POS API.
 */
interface ResponseValueMapperInterface
{
    /**
     * @param string|int $txType
     *
     * @return PosInterface::TX_TYPE_*|null
     */
    public function mapTxType($txType, ?string $paymentModel = null): ?string;

    /**
     * @param string                  $securityType
     * @param PosInterface::TX_TYPE_* $apiRequestTxType the transaction type of the API request.
     *
     * @return PosInterface::MODEL_*|null
     */
    public function mapSecureType(string $securityType, string $apiRequestTxType): ?string;

    /**
     * @param string|int              $currency
     * @param PosInterface::TX_TYPE_* $apiRequestTxType the transaction type of the API request.
     *
     * @return PosInterface::CURRENCY_*|null
     */
    public function mapCurrency($currency, string $apiRequestTxType): ?string;

    /**
     * maps order status of status and history requests.
     * If the order status is not mapped, it should return the original value.
     *
     * @param string|int $orderStatus
     *
     * @return PosInterface::PAYMENT_STATUS_*|string|int
     */
    public function mapOrderStatus($orderStatus);
}

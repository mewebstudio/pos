<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\PosInterface;

interface PaymentResponseMapperInterface
{
    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     *
     * @param array<string, mixed> $rawPaymentResponseData
     * @param string               $txType
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array;

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param array<string, string>      $raw3DAuthResponseData
     * @param array<string, string>|null $rawPaymentResponseData null when payment request was not made
     * @param string                     $txType
     * @param array<string, mixed>       $order
     *
     * @return array<string, string|float|null>
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, array $order): array;

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param array<string, string> $raw3DAuthResponseData
     * @param string                $txType
     * @param array<string, mixed>  $order
     *
     * @return array<string, mixed>
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array;

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param array<string, string> $raw3DAuthResponseData
     * @param string                $txType
     * @param array<string, mixed>  $order
     *
     * @return array<string, mixed>
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array;

    /**
     * extracts MD/3D auth status from the 3D Auth responses
     *
     * @param array<string, mixed> $raw3DAuthResponseData
     *
     * @return string|null numeric string
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string;

    /**
     * @param string|null $mdStatus
     *
     * @return bool
     */
    public function is3dAuthSuccess(?string $mdStatus): bool;
}

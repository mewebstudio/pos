<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\PosInterface;

interface SoapClientInterface
{
    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return bool
     */
    public static function supports(string $gatewayClass): bool;

    /**
     * @param PosInterface::TX_TYPE_* $txType
     * @param PosInterface::MODEL_*   $paymentModel
     * @param array<string, mixed>    $requestData
     * @param array<string, mixed>    $order
     * @param string                  $soapAction
     * @param string|null             $url
     * @param array<string, mixed>    $options Soap Request Options
     *
     * @return array<string, mixed> soap result
     *
     * @throws \SoapFault
     */
    public function call(string $txType, string $paymentModel, array $requestData, array $order, string $soapAction = null, string $url = null, array $options = []): array;

    /**
     * @return bool
     */
    public function isTestMode(): bool;

    /**
     * @param bool $isTestMode
     *
     * @return void
     */
    public function setTestMode(bool $isTestMode): void;
}

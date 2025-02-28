<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;

interface HttpClientInterface
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
     * @param string|null             $url
     * @param AbstractPosAccount      $account
     * @param bool                    $encode
     * @param bool                    $decode
     *
     * @return string|array<string, mixed>
     */
    public function request(string $txType, string $paymentModel, array $requestData, array $order, string $url = null, AbstractPosAccount $account = null, bool $encode = true, bool $decode = true);

    /**
     * todo maybe not needed
     */
    public function setTestMode(bool $isTestMode): void;

    public function isTestMode(): bool;
}

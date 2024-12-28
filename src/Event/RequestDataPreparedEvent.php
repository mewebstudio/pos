<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Event;

use Mews\Pos\PosInterface;

/**
 * This event is generated when an API request data is prepared.
 * By listening to this event you can update request data before it is sent to the bank API.
 */
class RequestDataPreparedEvent
{
    /** @var array<string, mixed> */
    private array $requestData;

    /** @var array<string, mixed> */
    private array $order;

    private string $bank;

    /** @var PosInterface::TX_TYPE_* */
    private string $txType;

    /** @var PosInterface::MODEL_* */
    private string $paymentModel;

    /** @var class-string<PosInterface> */
    private string $gatewayClass;

    /**
     * @phpstan-param PosInterface::TX_TYPE_*    $txType
     * @phpstan-param PosInterface::MODEL_*      $paymentModel
     * @phpstan-param class-string<PosInterface> $gatewayClass
     *
     * @param array<string, mixed> $requestData
     * @param string               $bank
     * @param string               $txType
     * @param string               $gatewayClass
     * @param array<string, mixed> $order
     * @param string               $paymentModel
     */
    public function __construct(
        array  $requestData,
        string $bank,
        string $txType,
        string $gatewayClass,
        array  $order,
        string $paymentModel
    ) {
        $this->requestData  = $requestData;
        $this->bank         = $bank;
        $this->txType       = $txType;
        $this->gatewayClass = $gatewayClass;
        $this->order        = $order;
        $this->paymentModel = $paymentModel;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequestData(): array
    {
        return $this->requestData;
    }

    /**
     * @param array<string, mixed> $requestData
     *
     * @return self
     */
    public function setRequestData(array $requestData): self
    {
        $this->requestData = $requestData;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(): array
    {
        return $this->order;
    }

    /**
     * @return PosInterface::TX_TYPE_*
     */
    public function getTxType(): string
    {
        return $this->txType;
    }

    /**
     * @return PosInterface::MODEL_*
     */
    public function getPaymentModel(): string
    {
        return $this->paymentModel;
    }

    /**
     * @return string
     */
    public function getBank(): string
    {
        return $this->bank;
    }

    /**
     * @return class-string<PosInterface>
     */
    public function getGatewayClass(): string
    {
        return $this->gatewayClass;
    }
}

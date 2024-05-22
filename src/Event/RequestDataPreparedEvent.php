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

    private string $bank;

    /** @var PosInterface::TX_TYPE_* */
    private string $txType;

    /** @var class-string<PosInterface> */
    private string $gatewayClass;

    /**
     * @phpstan-param PosInterface::TX_TYPE_*    $txType
     * @phpstan-param class-string<PosInterface> $gatewayClass
     *
     * @param array<string, mixed> $requestData
     * @param string               $bank
     * @param string               $txType
     * @param string               $gatewayClass
     */
    public function __construct(
        array  $requestData,
        string $bank,
        string $txType,
        string $gatewayClass
    ) {
        $this->requestData  = $requestData;
        $this->bank         = $bank;
        $this->txType       = $txType;
        $this->gatewayClass = $gatewayClass;
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
     * @return PosInterface::TX_TYPE_*
     */
    public function getTxType(): string
    {
        return $this->txType;
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

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
    private $requestData;

    /** @var string */
    private $bank;

    /** @var PosInterface::TX_* */
    private $txType;

    /**
     * @phpstan-param PosInterface::TX_* $txType
     *
     * @param array<string, mixed> $requestData
     * @param string               $bank
     * @param string               $txType
     */
    public function __construct(
        array  $requestData,
        string $bank,
        string $txType
    ) {
        $this->requestData = $requestData;
        $this->bank        = $bank;
        $this->txType = $txType;
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
     * @return PosInterface::TX_*
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
}

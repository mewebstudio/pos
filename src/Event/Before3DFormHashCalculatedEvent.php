<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Event;

use Mews\Pos\PosInterface;

/**
 * This event is generated before the hash of 3D form data is calculated.
 * By listening to this event you can update 3D form input data before the hash is calculated,
 * if changes in input data are used while calculating the hash.
 */
class Before3DFormHashCalculatedEvent
{
    /** @var array<string, string> */
    private array $formInputs;

    private string $bank;

    /** @var PosInterface::TX_TYPE_PAY_* */
    private string $txType;

    /** @var PosInterface::MODEL_3D_* */
    private string $paymentModel;

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     * @phpstan-param PosInterface::MODEL_3D_*    $paymentModel
     *
     * @param array<string, string> $formInputs
     * @param string                $bank
     * @param string                $txType
     * @param string                $paymentModel
     */
    public function __construct(array $formInputs, string $bank, string $txType, string $paymentModel)
    {
        $this->formInputs   = $formInputs;
        $this->bank         = $bank;
        $this->txType       = $txType;
        $this->paymentModel = $paymentModel;
    }

    /**
     * @return PosInterface::MODEL_3D_*
     */
    public function getPaymentModel(): string
    {
        return $this->paymentModel;
    }

    /**
     * @return PosInterface::TX_TYPE_PAY_*
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
     * @return array<string, string>
     */
    public function getFormInputs(): array
    {
        return $this->formInputs;
    }

    /**
     * @param array<string, string> $formInputs
     *
     * @return Before3DFormHashCalculatedEvent
     */
    public function setFormInputs(array $formInputs): self
    {
        $this->formInputs = $formInputs;

        return $this;
    }
}

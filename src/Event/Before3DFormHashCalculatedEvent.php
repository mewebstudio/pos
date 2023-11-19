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
class Before3DFormHashCalculatedEvent extends RequestDataPreparedEvent
{
    /** @var PosInterface::MODEL_3D_* */
    private string $paymentModel;

    /**
     * @param PosInterface::MODEL_3D_* $paymentModel
     *
     * @inheritdoc
     */
    public function __construct(array $requestData, string $bank, string $txType, string $paymentModel)
    {
        parent::__construct($requestData, $bank, $txType);

        $this->paymentModel = $paymentModel;
    }

    /**
     * @return PosInterface::MODEL_3D_*
     */
    public function getPaymentModel(): string
    {
        return $this->paymentModel;
    }
}

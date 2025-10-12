<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\PayForPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\PayForPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PayForPos
 */
class PayForPos extends AbstractHttpGateway
{
    /** @var string */
    public const NAME = 'PayForPOS';

    /** @var PayForAccount */
    protected AbstractPosAccount $account;

    /** @var PayForPosRequestDataMapper */
    protected RequestDataMapperInterface$requestDataMapper;

    /** @var PayForPosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_3D_HOST,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_3D_HOST,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_POST_AUTH  => true,
        PosInterface::TX_TYPE_STATUS         => true,
        PosInterface::TX_TYPE_CANCEL         => true,
        PosInterface::TX_TYPE_REFUND         => true,
        PosInterface::TX_TYPE_REFUND_PARTIAL => true,
        PosInterface::TX_TYPE_HISTORY        => true,
        PosInterface::TX_TYPE_ORDER_HISTORY  => true,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => true,
    ];

    /** @return PayForAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface
    {
        $postParameters = $request->request;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;

        if (!$this->is3DAuthSuccess($postParameters->all())) {
            $this->response = $this->responseDataMapper->map3DPaymentData($postParameters->all(), null, $txType, $order);

            return $this;
        }

        if (
            !$this->is3DHashCheckDisabled()
            && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $postParameters->all())
        ) {
            throw new HashMismatchException();
        }

        // valid ProcReturnCode is V033 in case of success 3D Authentication
        $requestData = $this->requestDataMapper->create3DPaymentRequestData(
            $this->account,
            $order,
            $txType,
            $postParameters->all()
        );

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $bankResponse = $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order
        );

        $this->response = $this->responseDataMapper->map3DPaymentData($postParameters->all(), $bankResponse, $txType, $order);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface
    {
        $this->response = $this->responseDataMapper->map3DPayResponseData($request->request->all(), $txType, $order);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface
    {
        $this->response = $this->responseDataMapper->map3DHostResponseData($request->request->all(), $txType, $order);

        return $this;
    }

    /**
     * Satış işlemi ile farklı batchtlerde olmalıdır.
     *
     * @inheritDoc
     */
    public function refund(array $order): PosInterface
    {
        return parent::refund($order);
    }

    /**
     * Fetches Transaction history (both failed and successful) for the given date ReqDate
     * Note: history request to gateway returns JSON response
     * @inheritDoc
     */
    public function history(array $data): PosInterface
    {
        return parent::history($data);
    }

    /**
     * Fetches transaction history (both failed and successful, refund|pre|post|cancel) related to the queried order
     * Note: history request to gateway returns JSON response
     * @inheritDoc
     */
    public function orderHistory(array $order): PosInterface
    {
        return parent::orderHistory($order);
    }


    /**
     * {@inheritDoc}
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $creditCard = null, bool $createWithoutCard = true): array
    {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard, $createWithoutCard);

        $this->logger->debug('preparing 3D form data');

        return $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $this->get3DGatewayURL($paymentModel),
            $creditCard
        );
    }
}

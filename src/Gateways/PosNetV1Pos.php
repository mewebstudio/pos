<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\Request;

class PosNetV1Pos extends AbstractHttpGateway
{
    /** @var string */
    public const NAME = 'PosNetV1';

    /** @var PosNetAccount */
    protected AbstractPosAccount $account;

    /** @var PosNetV1PosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var PosNetV1PosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_POST_AUTH  => true,
        PosInterface::TX_TYPE_STATUS         => true,
        PosInterface::TX_TYPE_CANCEL         => true,
        PosInterface::TX_TYPE_REFUND         => true,
        PosInterface::TX_TYPE_REFUND_PARTIAL => true,
        PosInterface::TX_TYPE_HISTORY        => false,
        PosInterface::TX_TYPE_ORDER_HISTORY  => false,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => true,
    ];

    /** @return PosNetAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * Kullanıcı doğrulama sonucunun sorgulanması ve verilerin doğruluğunun teyit edilmesi için kullanılır.
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface
    {
        $request      = $request->request;
        $paymentModel = self::MODEL_3D_SECURE;

        if (!$this->is3DAuthSuccess($request->all())) {
            $this->response = $this->responseDataMapper->map3DPaymentData($request->all(), null, $txType, $order);

            return $this;
        }

        if (
            !$this->is3DHashCheckDisabled()
            && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())
        ) {
            throw new HashMismatchException();
        }

        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $request->all());

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

        $provisionResponse = $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
        );
        $this->logger->debug('send $provisionResponse', ['$provisionResponse' => $provisionResponse]);

        $this->response = $this->responseDataMapper->map3DPaymentData($request->all(), $provisionResponse, $txType, $order);
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
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

    /**
     * @inheritDoc
     */
    public function history(array $data): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     */
    public function orderHistory(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }
}

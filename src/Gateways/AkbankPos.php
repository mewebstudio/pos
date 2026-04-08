<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\AkbankPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\AkbankPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;

/**
 * @since 1.1.0
 *
 * @link https://sanalpos-prep.akbank.com/#entry
 */
class AkbankPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'AkbankPos';

    /** @var AkbankPosAccount */
    protected AbstractPosAccount $account;

    /** @var AkbankPosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

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
        PosInterface::TX_TYPE_STATUS         => false,
        PosInterface::TX_TYPE_CANCEL         => true,
        PosInterface::TX_TYPE_REFUND         => true,
        PosInterface::TX_TYPE_REFUND_PARTIAL => true,
        PosInterface::TX_TYPE_ORDER_HISTORY  => true,
        PosInterface::TX_TYPE_HISTORY        => true,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => true,
    ];

    /** @return AkbankPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(array $gatewayResponseData, array $order, string $txType, ?CreditCardInterface $creditCard = null): array
    {
        $paymentModel   = PosInterface::MODEL_3D_SECURE;

        if (!$this->is3DAuthSuccess($gatewayResponseData)) {
            $this->response = $this->responseDataMapper->map3DPaymentData(
                $gatewayResponseData,
                null,
                $txType,
                $order
            );

            return $this->response;
        }

        if (
            !$this->is3DHashCheckDisabled()
            && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $gatewayResponseData)
        ) {
            throw new HashMismatchException();
        }

        $requestData = $this->requestDataMapper->create3DPaymentRequestData(
            $this->account,
            $order,
            $txType,
            $gatewayResponseData
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

        /** @var array<string, mixed> $provisionResponse */
        $provisionResponse = $this->clientStrategy->getClient(
            $txType,
            $paymentModel,
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            null,
            $this->account
        );

        $this->response = $this->responseDataMapper->map3DPaymentData(
            $gatewayResponseData,
            $provisionResponse,
            $txType,
            $order
        );
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(array $gatewayResponseData, array $order, string $txType): array
    {
        if (
            !$this->is3DHashCheckDisabled()
            && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $gatewayResponseData)
        ) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DPayResponseData($gatewayResponseData, $txType, $order);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(array $gatewayResponseData, array $order, string $txType): array
    {
        if (
            !$this->is3DHashCheckDisabled()
            && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $gatewayResponseData)
        ) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DHostResponseData($gatewayResponseData, $txType, $order);

        return $this->response;
    }

    /**
     * @inheritDoc
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard = null, bool $createWithoutCard = true): array
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
    public function status(array $order): array
    {
        throw new UnsupportedTransactionTypeException();
    }
}

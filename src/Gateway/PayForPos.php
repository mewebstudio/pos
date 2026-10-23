<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateway;

use Mews\Pos\DataMapper\Request\Mapper\PayForPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Response\Mapper\PayForPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\PayForPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedFormFormatException;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Class PayForPos
 */
class PayForPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'PayForPOS';

    /** @var PayForPosAccount */
    protected AbstractPosAccount $account;

    /** @var PayForPosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

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
        PosInterface::TX_TYPE_ORDER_HISTORY  => true,
    ];

    /** @return PayForPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(array $gatewayResponseData, array $order, string $txType, ?CreditCardInterface $creditCard = null): array
    {
        $paymentModel = PosInterface::MODEL_3D_SECURE;

        if (!$this->is3dAuthSuccess($gatewayResponseData)) {
            $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponseData, null, $txType, $order);

            return $this->response;
        }

        if (
            !$this->is3DHashCheckDisabled()
            && !$this->crypt->check3DHash($this->account, $gatewayResponseData)
        ) {
            throw new HashMismatchException();
        }

        // valid ProcReturnCode is V033 in case of success 3D Authentication
        $requestData = $this->requestDataMapper->create3DPaymentRequestData(
            $this->account,
            $order,
            $txType,
            $gatewayResponseData
        );

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBankName(),
            $txType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bankName'    => $event->getBankName(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array<string, mixed> $bankResponse */
        $bankResponse = $this->clientStrategy->getClient(
            $txType,
            $paymentModel,
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order
        );

        $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponseData, $bankResponse, $txType, $order);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(array $gatewayResponseData, array $order, string $txType): array
    {
        if (
            !$this->is3DHashCheckDisabled()
            && !$this->crypt->check3DHash($this->account, $gatewayResponseData)
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
            && !$this->crypt->check3DHash($this->account, $gatewayResponseData)
        ) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DHostResponseData($gatewayResponseData, $txType, $order);

        return $this->response;
    }

    /**
     * Satış işlemi ile farklı batchtlerde olmalıdır.
     *
     * @inheritDoc
     */
    public function refund(array $order): array
    {
        return parent::refund($order);
    }

    /**
     * Fetches transaction history (both failed and successful, refund|pre|post|cancel) related to the queried order
     * Note: history request to gateway returns JSON response
     *
     * @inheritDoc
     */
    public function orderHistory(array $order): array
    {
        return parent::orderHistory($order);
    }


    /**
     * {@inheritDoc}
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard = null, bool $createWithoutCard = false, ?string $formFormat = null)
    {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard, $createWithoutCard);

        if ($formFormat === PosInterface::FORM_FORMAT_HTML && PosInterface::MODEL_3D_HOST === $paymentModel) {
            throw new UnsupportedFormFormatException();
        }

        $this->logger->debug('preparing 3D form data', [
            'payment_model' => $paymentModel,
            'tx_type'       => $txType,
            'order'         => $order,
        ]);

        if ($formFormat === PosInterface::FORM_FORMAT_HTML) {
            $htmlForm = $this->initialize3DForm($order, $paymentModel, $txType, $creditCard);

            if ('' === $htmlForm) {
                throw new \RuntimeException('3D form verisi oluşturulamadı');
            }

            return $htmlForm;
        }

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
     * @param array<string, mixed>                                              $order
     * @param PosInterface::MODEL_3D_*                                          $paymentModel
     * @param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $orderTxType
     * @param CreditCardInterface|null                                          $creditCard
     *
     * @return string
     *
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    private function initialize3DForm(array $order, string $paymentModel, string $orderTxType, ?CreditCardInterface $creditCard = null): string
    {
        $requestTxType = PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD;
        $requestData   = $this->requestDataMapper->create3DFormInitializeRequestData(
            $this->account,
            $order,
            $paymentModel,
            $orderTxType,
            $creditCard
        );

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBankName(),
            $requestTxType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bankName'    => $event->getBankName(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var string $result */
        $result = $this->clientStrategy->getClient(
            $requestTxType,
            $paymentModel,
        )->request(
            $requestTxType,
            $paymentModel,
            $requestData,
            $order
        );

        return $result;
    }
}

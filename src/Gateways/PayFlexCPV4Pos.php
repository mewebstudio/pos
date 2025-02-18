<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * PayFlex Common Payment (Ortak Ödeme) ISD v4.0
 * Dokumanlar: http://sanalpos.innova.com.tr/
 */
class PayFlexCPV4Pos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'PayFlex-Common-Payment-V4';

    /** @var PayFlexAccount */
    protected AbstractPosAccount $account;

    /** @var PayFlexCPV4PosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var PayFlexCPV4PosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_3D_HOST,
        ],
        PosInterface::TX_TYPE_PAY_POST_AUTH  => false,
        PosInterface::TX_TYPE_STATUS         => false,
        PosInterface::TX_TYPE_CANCEL         => false,
        PosInterface::TX_TYPE_REFUND         => false,
        PosInterface::TX_TYPE_REFUND_PARTIAL => false,
        PosInterface::TX_TYPE_HISTORY        => false,
        PosInterface::TX_TYPE_ORDER_HISTORY  => false,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => true,
    ];

    /** @return PayFlexAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     *
     * @throws ClientExceptionInterface
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface
    {
        $resultCode = $request->query->get('Rc');
        if (null !== $resultCode && $this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== $resultCode) {
            $this->logger->error('received error response from the bank', $request->query->all());
            $this->response = $this->responseDataMapper->map3DPayResponseData($request->query->all(), $txType, $order);

            return $this;
        }

        $bankResponse = $this->get3DPaymentStatus($request, $order);

        $this->response = $this->responseDataMapper->map3DPayResponseData($bankResponse, $txType, $order);

        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @throws ClientExceptionInterface
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface
    {
        $resultCode = $request->query->get('Rc');
        if (null !== $resultCode && $this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== $resultCode) {
            $this->logger->error('received error response from the bank', $request->query->all());
            $this->response = $this->responseDataMapper->map3DHostResponseData($request->query->all(), $txType, $order);

            return $this;
        }

        $bankResponse = $this->get3DPaymentStatus($request, $order);

        $this->response = $this->responseDataMapper->map3DHostResponseData($bankResponse, $txType, $order);

        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment(array $order, CreditCardInterface $creditCard, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPostPayment(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     */
    public function status(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     */
    public function refund(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     */
    public function cancel(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
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

    /**
     * {@inheritDoc}
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $creditCard = null, bool $createWithoutCard = true): array
    {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard, $createWithoutCard);

        /** @var array{CommonPaymentUrl: string|null, PaymentToken: string|null, ErrorCode: string|null, ResponseMessage: string|null} $data */
        $data = $this->registerPayment($order, $txType, $paymentModel, $creditCard);

        if (null !== $data['ErrorCode']) {
            $this->logger->error('payment register fail response', $data);
            throw new \RuntimeException('İşlem gerçekleştirilemiyor');
        }

        $this->logger->debug('preparing 3D form data');

        return $this->requestDataMapper->create3DFormData(
            null,
            [],
            null,
            null,
            null,
            null,
            $data
        );
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    protected function send($contents, string $txType, string $paymentModel, string $url): array
    {
        $this->logger->debug('sending request', ['url' => $url]);
        if (!\is_string($contents)) {
            throw new \InvalidArgumentException(\sprintf('Argument type must be string, %s provided.', \gettype($contents)));
        }

        $response = $this->client->post($url, [
            'headers' => [
                'Accept'       => 'text/xml',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => $contents,
        ]);

        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        $responseContent = $response->getBody()->getContents();

        return $this->data = $this->serializer->decode($responseContent, $txType);
    }

    /**
     *
     * ORTAK ÖDEME SİSTEMİNE İŞLEM KAYDETME
     *
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param array<string, int|string|float|null> $order
     * @param string                               $txType
     * @param string                               $paymentModel
     * @param CreditCardInterface|null             $creditCard
     *
     * Basarili durumda donen cevap formati: array{CommonPaymentUrl: string, PaymentToken: string, ErrorCode: null,
     * ResponseMessage: null} Basarisiz durumda donen cevap formati: array{CommonPaymentUrl: null, PaymentToken: null,
     * ErrorCode: string, ResponseMessage: string}
     *
     * @return array{CommonPaymentUrl: string|null, PaymentToken: string|null, ErrorCode: string|null, ResponseMessage: string|null}
     *
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    private function registerPayment(array $order, string $txType, string $paymentModel, CreditCardInterface $creditCard = null): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $this->account,
            $order,
            $txType,
            $paymentModel,
            $creditCard
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

        $contents = $this->serializer->encode($requestData, $txType);

        /** @var array{CommonPaymentUrl: string|null, PaymentToken: string|null, ErrorCode: string|null, ResponseMessage: string|null} $response */
        $response = $this->send(
            $contents,
            $txType,
            $paymentModel,
            $this->get3DGatewayURL()
        );

        return $response;
    }

    /**
     * get 3D Payment status  to make sure that payment was successful
     * @param Request              $request
     * @param array<string, mixed> $order
     *
     * @return  array{ErrorCode: string}|array{
     *      Rc: string,
     *      AuthCode: string,
     *      TransactionId: string,
     *      PaymentToken: string,
     *      MaskedPan: string}|array{
     *      Rc: string,
     *      Message: string,
     *      TransactionId: string,
     *      PaymentToken: string}
     *
     * @throws ClientExceptionInterface
     */
    private function get3DPaymentStatus(Request $request, array $order): array
    {
        $txType       = PosInterface::TX_TYPE_STATUS;
        $paymentModel = PosInterface::MODEL_NON_SECURE;

        /** @var array{TransactionId: string, PaymentToken: string} $queryParams */
        $queryParams = $request->query->all();

        // Burda odemenin basarili olup olmadigini sorguluyoruz.
        $requestData = $this->requestDataMapper->create3DPaymentStatusRequestData($this->account, $queryParams);

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

        $contents = $this->serializer->encode($requestData, $txType);

        /**
         * sending request to make sure that payment was successful
         */
        return $this->send(
            $contents,
            $txType,
            $paymentModel,
            $this->getApiURL()
        );
    }
}

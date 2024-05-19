<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifKatilimPosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Vakif Katilim banki desteleyen Gateway
 * V2.7
 */
class VakifKatilimPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'VakifKatilim';

    /** @var KuveytPosAccount */
    protected AbstractPosAccount $account;

    /** @var VakifKatilimPosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var VakifKatilimPosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH      => [
            PosInterface::MODEL_NON_SECURE,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_3D_HOST,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH  => [
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_POST_AUTH => true,
        PosInterface::TX_TYPE_STATUS        => true,
        PosInterface::TX_TYPE_CANCEL        => true,
        PosInterface::TX_TYPE_REFUND        => true,
        PosInterface::TX_TYPE_HISTORY       => true,
        PosInterface::TX_TYPE_ORDER_HISTORY => true,
    ];

    /** @return KuveytPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function getApiURL(string $txType = null, string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null !== $txType && null !== $paymentModel) {
            return parent::getApiURL().'/'.$this->getRequestURIByTransactionType($txType, $paymentModel, $orderTxType);
        }

        return parent::getApiURL();
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
        $this->response = $this->responseDataMapper->map3DHostResponseData($request->request->all(), $txType, $order);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $creditCard = null): array
    {
        $this->logger->debug('preparing 3D form data');

        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            return $this->requestDataMapper->create3DFormData($this->account, $order, $paymentModel, $txType, $this->get3DHostGatewayURL());
        }

        $gatewayUrl = $this->get3DGatewayURL();
        $response   = $this->sendEnrollmentRequest($this->account, $order, $paymentModel, $txType, $gatewayUrl, $creditCard);

        return $this->requestDataMapper->create3DFormData($this->account, $response['form_inputs'], $paymentModel, $txType, $response['gateway'], $creditCard);
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface
    {
        $gatewayResponse = $request->request->all();

        if (!$this->is3DAuthSuccess($gatewayResponse)) {
            $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponse, null, $txType, $order);

            return $this;
        }

        $this->logger->debug('finishing payment');

        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $gatewayResponse);

        $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), $txType);
        $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $contents     = $this->serializer->encode($requestData, $txType);
        $bankResponse = $this->send($contents, $txType, PosInterface::MODEL_3D_SECURE);

        $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponse, $bankResponse, $txType, $order);
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }


    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    protected function send($contents, string $txType, string $paymentModel, string $url = null): array
    {
        $url ??= $this->getApiURL($txType, $paymentModel);

        $this->logger->debug('sending request', ['url' => $url]);
        $body     = [
            'body'    => $contents,
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF-8',
            ],
        ];
        $response = $this->client->post($url, $body);
        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        return $this->data = $this->serializer->decode($response->getBody()->getContents(), $txType);
    }

    /**
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param KuveytPosAccount                     $kuveytPosAccount
     * @param array<string, int|string|float|null> $order
     * @param string                               $paymentModel
     * @param string                               $txType
     * @param non-empty-string                     $gatewayURL
     * @param CreditCardInterface|null             $creditCard
     *
     * @return array{gateway: string, form_inputs: array<string, string>}
     *
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    private function sendEnrollmentRequest(KuveytPosAccount $kuveytPosAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData($kuveytPosAccount, $order, $paymentModel, $txType, $creditCard);

        $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), $txType);
        $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $data = $this->serializer->encode($requestData, $txType);

        /**
         * @var array{form_inputs: array<string, string>, gateway: string} $decodedResponse
         */
        $decodedResponse = $this->send($data, $txType, $paymentModel, $gatewayURL);

        return $decodedResponse;
    }


    /**
     * @phpstan-param PosInterface::TX_TYPE_*     $txType
     * @phpstan-param PosInterface::MODEL_*       $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $orderTxType
     *
     * @return string
     *
     * @throws UnsupportedTransactionTypeException
     */
    private function getRequestURIByTransactionType(string $txType, string $paymentModel, ?string $orderTxType = null): string
    {
        $orderTxType ??= PosInterface::TX_TYPE_PAY_AUTH;

        $arr = [
            PosInterface::TX_TYPE_PAY_AUTH      => [
                PosInterface::MODEL_NON_SECURE => 'Non3DPayGate',
                PosInterface::MODEL_3D_SECURE  => 'ThreeDModelProvisionGate',
            ],
            PosInterface::TX_TYPE_PAY_PRE_AUTH  => [
                PosInterface::MODEL_NON_SECURE => 'PreAuthorizaten',
            ],
            PosInterface::TX_TYPE_PAY_POST_AUTH => 'PreAuthorizatenClose',
            PosInterface::TX_TYPE_CANCEL        => [
                PosInterface::MODEL_NON_SECURE => [
                    PosInterface::TX_TYPE_PAY_AUTH     => 'SaleReversal',
                    PosInterface::TX_TYPE_PAY_PRE_AUTH => 'PreAuthorizationReversal',
                ],
            ],
            PosInterface::TX_TYPE_REFUND        => [
                PosInterface::MODEL_NON_SECURE => [
                    PosInterface::TX_TYPE_PAY_AUTH     => 'DrawBack',
                    PosInterface::TX_TYPE_PAY_PRE_AUTH => 'PreAuthorizationDrawBack',
                ],
            ],
            PosInterface::TX_TYPE_STATUS        => 'SelectOrderByMerchantOrderId',
            PosInterface::TX_TYPE_ORDER_HISTORY => 'SelectOrder',
            PosInterface::TX_TYPE_HISTORY       => 'SelectOrder',
        ];

        if (\is_string($arr[$txType])) {
            return $arr[$txType];
        }

        if (!isset($arr[$txType][$paymentModel])) {
            throw new UnsupportedTransactionTypeException();
        }

        if (\is_array($arr[$txType][$paymentModel])) {
            return $arr[$txType][$paymentModel][$orderTxType];
        }

        return $arr[$txType][$paymentModel];
    }
}

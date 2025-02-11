<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\ParamPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ParamPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @since 1.6.0
 * Documentation:
 * @link https://dev.param.com.tr
 */
class ParamPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'ParamPos';

    /** @var ParamPosAccount */
    protected AbstractPosAccount $account;

    /** @var ParamPosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var ParamPosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH     => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_3D_HOST,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_NON_SECURE,
        ],

        PosInterface::TX_TYPE_HISTORY        => true,
        PosInterface::TX_TYPE_ORDER_HISTORY  => false,
        PosInterface::TX_TYPE_PAY_POST_AUTH  => true,
        PosInterface::TX_TYPE_CANCEL         => true,
        PosInterface::TX_TYPE_REFUND         => true,
        PosInterface::TX_TYPE_REFUND_PARTIAL => true,
        PosInterface::TX_TYPE_STATUS         => true,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => true,
    ];

    /**
     * @return ParamPosAccount
     */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function getApiURL(string $txType = null, string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            if (!isset($this->config['gateway_endpoints']['payment_api_2'])) {
                throw new \RuntimeException('3D Host ödemeyi kullanabilmek için "payment_api_2" endpointi tanımlanmalıdır.');
            }

            return $this->config['gateway_endpoints']['payment_api_2'];
        }

        return parent::getApiURL($txType, $paymentModel, $orderTxType);
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface
    {
        if ($request->request->get('TURKPOS_RETVAL_Sonuc') !== null) {
            // Doviz ile odeme
            return $this->make3DPayPayment($request, $order, $txType);
        }

        $request = $request->request;

        if (!$this->is3DAuthSuccess($request->all())) {
            $this->response = $this->responseDataMapper->map3DPaymentData(
                $request->all(),
                null,
                $txType,
                $order
            );

            return $this;
        }

        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())) {
            throw new HashMismatchException();
        }

        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $request->all());

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $order,
            PosInterface::MODEL_3D_SECURE
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

        $contents          = $this->serializer->encode($requestData, $txType);
        $provisionResponse = $this->send(
            $contents,
            $txType,
            PosInterface::MODEL_3D_SECURE,
            $this->getApiURL($txType)
        );

        $this->response = $this->responseDataMapper->map3DPaymentData(
            $request->all(),
            $provisionResponse,
            $txType,
            $order
        );
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface
    {
        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->request->all())) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DPayResponseData($request->request->all(), $txType, $order);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface
    {
        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->request->all())) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DHostResponseData($request->request->all(), $txType, $order);

        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $creditCard = null, bool $createWithoutCard = true)
    {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard);

        $data = $this->registerPayment($order, $paymentModel, $txType, $creditCard);

        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            return $this->requestDataMapper->create3DFormData(
                $this->account,
                $order,
                $paymentModel,
                $txType,
                $this->get3DGatewayURL($paymentModel),
                null,
                $data
            );
        }

        $result = $data['TP_WMD_UCDResponse']['TP_WMD_UCDResult']
            ?? $data['TP_Islem_Odeme_WDResponse']['TP_Islem_Odeme_WDResult']
            ?? $data['TP_Islem_Odeme_OnProv_WMDResponse']['TP_Islem_Odeme_OnProv_WMDResult'] // on provizyon
            ?? $data['Pos_OdemeResponse']['Pos_OdemeResult'] // 3D Pay
        ;
        if ($result['Sonuc'] < 1) {
            $this->logger->error('soap error response', $result);

            throw new \RuntimeException($result['Sonuc_Str'], $result['Sonuc']);
        }

        return $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            null,
            null,
            $data
        );
    }

    /**
     * @inheritDoc
     */
    public function customQuery(array $requestData, string $apiUrl = null): PosInterface
    {
        $apiUrl ??= $this->getApiURL(PosInterface::TX_TYPE_CUSTOM_QUERY);

        return parent::customQuery($requestData, $apiUrl);
    }

    /**
     * @inheritDoc
     */
    public function orderHistory(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    protected function send($contents, string $txType, string $paymentModel, string $url): array
    {
        $this->logger->debug('sending request', ['url' => $url]);


        $response = $this->client->post($url, [
            'headers' => [
                'Content-Type' => 'text/xml',
            ],
            'body'    => $contents,
        ]);

        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        $responseContent = $response->getBody()->getContents();

        $decodedData = $this->serializer->decode($responseContent, $txType);

        if (isset($decodedData['soap:Fault'])) {
            $this->logger->error('soap error response', [
                'status_code' => $response->getStatusCode(),
                'response' => $decodedData,
            ]);

            throw new \RuntimeException($decodedData['soap:Fault']['faultstring'] ?? 'Bankaya istek başarısız!');
        }

        return $this->data = $decodedData;
    }

    /**
     * @param array<string, int|string|float|null>                              $order
     * @param PosInterface::MODEL_3D_*                                          $paymentModel
     * @param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    private function registerPayment(array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $this->account,
            $order,
            $creditCard,
            $txType,
            $paymentModel
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

        $requestData = $this->serializer->encode($requestData, $txType);

        return $this->send(
            $requestData,
            $txType,
            $paymentModel,
            $this->getApiURL($txType, $paymentModel)
        );
    }
}

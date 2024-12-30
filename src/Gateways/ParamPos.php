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
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;
use SoapFault;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarExporter\VarExporter;

/**
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

    //todo
    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH     => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_3D_HOST,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH => [
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_3D_HOST,
        ],

        PosInterface::TX_TYPE_HISTORY        => false,
        PosInterface::TX_TYPE_ORDER_HISTORY  => true,
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

    // todo
//    /**
//     * @inheritDoc
//     *
//     * @throws UnsupportedTransactionTypeException
//     * @throws \InvalidArgumentException when transaction type or payment model are not provided
//     */
//    public function getApiURL(string $txType = null, string $paymentModel = null, ?string $orderTxType = null): string
//    {
//        if (null !== $txType && null !== $paymentModel) {
//            return parent::getApiURL().'/'.$this->getRequestURIByTransactionType($txType, $paymentModel);
//        }
//
//        throw new \InvalidArgumentException('Transaction type and payment model are required to generate API URL');
//    }

    // todo
    /**
     * @inheritDoc
     *
     * @param string $threeDSessionId
     */
    public function get3DGatewayURL(string $paymentModel = PosInterface::MODEL_3D_SECURE, string $threeDSessionId = null): string
    {
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            return parent::get3DGatewayURL($paymentModel).'/'.$threeDSessionId;
        }

        return parent::get3DGatewayURL($paymentModel);
    }

    // todo
    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface
    {
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
        dump($request->all());
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

        $contents          = $this->serializer->encode($requestData, 'TP_WMD_Pay');
        $provisionResponse = $this->send(
            $contents,
            $txType,
            PosInterface::MODEL_3D_SECURE,
            $this->getApiURL($txType)
        );
        echo VarExporter::export($provisionResponse);
        //dd($provisionResponse);

        $this->response = $this->responseDataMapper->map3DPaymentData(
            $request->all(),
            $provisionResponse,
            $txType,
            $order
        );
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    // todo
    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface
    {
        $request = $request->request;

        if ($this->is3DAuthSuccess($request->all()) && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DPayResponseData($request->all(), $txType, $order);

        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    // todo
    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface
    {
        $request = $request->request;

        if ($this->is3DAuthSuccess($request->all()) && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DHostResponseData($request->all(), $txType, $order);

        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $creditCard = null, bool $createWithoutCard = true): string
    {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard);

        $data = $this->registerPayment($order, $paymentModel, $txType, $creditCard);

        if (isset($data['soap:Fault'])) {
            $this->logger->error('soap error response', $data['soap:Fault']);

            throw new \RuntimeException($data['soap:Fault']['faultstring']);
        }

        $result = $data['TP_WMD_UCDResponse']['TP_WMD_UCDResult'];
        if ($result['Sonuc'] < 0) {
            $this->logger->error('soap error response', $result);

            throw new \RuntimeException($result['Sonuc_Str'], $result['Sonuc']);
        }

        return $result['UCD_HTML'];
    }

    // todo
    /**
     * @inheritDoc
     */
    public function customQuery(array $requestData, string $apiUrl = null): PosInterface
    {
        if (null === $apiUrl) {
            throw new \InvalidArgumentException('API URL is required for custom query');
        }

        return parent::customQuery($requestData, $apiUrl);
    }

    // todo
    /**
     * @inheritDoc
     */
    public function history(array $data): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    // todo
    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    protected function send($contents, string $txType, string $paymentModel, string $url): array
    {
       // dump($contents);
//        $contents = <<<HTML
/*<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>$contents</soap:Body></soap:Envelope>*/
//HTML;
        //dd($contents);
        //$contents = str_replace(['<TP_WMD_UCD>'], '<TP_WMD_UCD xmlns="https://turkpos.com.tr/">', $contents);
        //dump($contents);
        $this->logger->debug('sending request', ['url' => $url]);

//        return $this->data = $this->sendSoapRequest($contents, $txType, $url);
        $response = $this->client->post($url, [
            'headers' => [
                'Content-Type' => 'text/xml',
                //'Content-Length'  => strlen($contents),
            ],
            'body'    => $contents,
        ]);

        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

//        if ($response->getStatusCode() === 204) {
//            $this->logger->warning('response from api is empty');
//
//            return $this->data = [];
//        }

        $responseContent = $response->getBody()->getContents();

        dump('$responseContent', $responseContent);
        //dd($response->getStatusCode(), $responseContent);
        return $this->data = $this->serializer->decode($responseContent, $txType);
    }

    /**
     * @phpstan-param PosInterface::TX_*
     *
     * @param array<string, mixed> $contents
     * @param string               $txType
     * @param string               $url
     *
     * @return array<string, mixed>
     *
     * @throws SoapFault
     * @throws \RuntimeException
     */
    private function sendSoapRequest(array $contents, string $txType, string $url): array
    {
        $this->logger->debug('sending soap request', [
            'txType' => $txType,
            'url'    => $url,
        ]);

        $sslConfig = [
            'allow_self_signed' => true,
            'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ];
        if ($this->isTestMode()) {
            $sslConfig = [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
            ];
        }

        $options = [
            'trace'          => true,
            'encoding'       => 'UTF-8',
            'stream_context' => stream_context_create(['ssl' => $sslConfig]),
            'exceptions'     => true,
        ];


        $client = new \SoapClient($url, $options);
        try {
            //$contents = json_decode(json_encode($contents));
            dump($url, $contents);
//            $result = $client->TP_WMD_UCD($contents);
            $result = $client->__soapCall(
                //'Pos_Odeme',
                'TP_WMD_UCD',
                [$contents]
                //['parameters' => ['request' => $contents]]
            );
            dd($result);
        } catch (SoapFault $soapFault) {
            $this->logger->error('soap error response', [
                'message' => $soapFault->getMessage(),
            ]);

            throw $soapFault;
        }

        if (null === $result) {
            $this->logger->error('Bankaya istek başarısız!', [
                'response' => $result,
            ]);
            throw new \RuntimeException('Bankaya istek başarısız!');
        }

        $encodedResult = \json_encode($result);

        if (false === $encodedResult) {
            return [];
        }

        return $this->serializer->decode($encodedResult, $txType);
    }


    // todo
    /**
     * Ödeme İşlem Başlatma
     *
     * Ödeme formu ve Ortak Ödeme Sayfası ile ödeme işlemi başlatmak için ThreeDSessionId değeri üretilmelidir.
     * Bu servis 3D secure başlatılması için session açar ve sessionId bilgisini döner.
     * Bu servisten dönen ThreeDSessionId değeri ödeme formunda veya ortak ödeme sayfa çağırma işleminde kullanılır.
     *
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param array<string, int|string|float|null> $order
     * @param string                               $paymentModel
     * @param string                               $txType
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    private function registerPayment(array $order, string $paymentModel, string $txType, CreditCardInterface $creditCard): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $this->account,
            $order,
            $creditCard,
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

        $requestData = $this->serializer->encode($requestData, 'TP_WMD_UCD'); //todo TP_WMD_UCD

        return $this->send(
            $requestData,
            $txType,
            $paymentModel,
            $this->getApiURL($txType, $paymentModel)
        );
    }

    // todo
    /**
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     * @phpstan-param PosInterface::MODEL_*   $paymentModel
     *
     * @return string
     *
     * @throws UnsupportedTransactionTypeException
     */
    private function getRequestURIByTransactionType(string $txType, string $paymentModel): string
    {
        $arr = [
            PosInterface::TX_TYPE_PAY_AUTH       => [
                PosInterface::MODEL_NON_SECURE => 'Payment',
                PosInterface::MODEL_3D_PAY     => 'threeDPayment',
                PosInterface::MODEL_3D_HOST    => 'threeDPayment',
            ],
            PosInterface::TX_TYPE_PAY_PRE_AUTH   => [
                PosInterface::MODEL_3D_PAY  => 'threeDPreAuth',
                PosInterface::MODEL_3D_HOST => 'threeDPreAuth',
            ],
            PosInterface::TX_TYPE_PAY_POST_AUTH  => 'postAuth',
            PosInterface::TX_TYPE_CANCEL         => 'void',
            PosInterface::TX_TYPE_REFUND         => 'refund',
            PosInterface::TX_TYPE_REFUND_PARTIAL => 'refund',
            PosInterface::TX_TYPE_STATUS         => 'inquiry',
            PosInterface::TX_TYPE_ORDER_HISTORY  => 'history',
        ];

        if (!isset($arr[$txType])) {
            throw new UnsupportedTransactionTypeException();
        }

        if (\is_string($arr[$txType])) {
            return $arr[$txType];
        }

        if (!isset($arr[$txType][$paymentModel])) {
            throw new UnsupportedTransactionTypeException();
        }

        return $arr[$txType][$paymentModel];
    }
}

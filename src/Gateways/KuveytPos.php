<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Exception;
use InvalidArgumentException;
use LogicException;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use RuntimeException;
use SoapClient;
use SoapFault;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use function is_string;
use function urldecode;

/**
 * Kuveyt banki desteleyen Gateway
 */
class KuveytPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'KuveytPos';

    /** @var KuveytPosAccount */
    protected $account;

    /** @var KuveytPosRequestDataMapper */
    protected $requestDataMapper;

    /** @var KuveytPosResponseDataMapper */
    protected $responseDataMapper;

    /** @inheritdoc */
    protected static $supportedTransactions = [
        PosInterface::TX_PAY      => [
            PosInterface::MODEL_3D_SECURE,
        ],
        PosInterface::TX_PRE_PAY  => false,
        PosInterface::TX_POST_PAY => false,
        PosInterface::TX_STATUS   => true,
        PosInterface::TX_CANCEL   => true,
        PosInterface::TX_REFUND   => true,
        PosInterface::TX_HISTORY  => false,
    ];

    /** @return KuveytPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * Deniz bank dokumantasyonunda history sorgusu ile alakali hic bir bilgi yok
     * @inheritDoc
     */
    public function history(array $meta): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $card = null): array
    {
        $gatewayUrl = $this->get3DGatewayURL();
        $this->logger->debug('preparing 3D form data');

        return $this->getCommon3DFormData($this->account, $order, $paymentModel, $txType, $gatewayUrl, $card);
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment(array $order, CreditCardInterface $card, string $txType): PosInterface
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $card = null): PosInterface
    {
        $gatewayResponse = $request->request->get('AuthenticationResponse');
        if (!is_string($gatewayResponse)) {
            throw new LogicException('AuthenticationResponse is missing');
        }

        $gatewayResponse = urldecode($gatewayResponse);
        $gatewayResponse = $this->serializer->decode($gatewayResponse, $txType);

        $bankResponse   = null;
        $procReturnCode = $gatewayResponse['ResponseCode'];

        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $gatewayResponse)) {
            throw new HashMismatchException();
        }

        if ($this->responseDataMapper::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
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
            $bankResponse = $this->send($contents, $txType);
        } else {
            $this->logger->error('3d auth fail', ['proc_return_code' => $procReturnCode]);
        }

        $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponse, $bankResponse);
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }


    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    protected function send($contents, string $txType, string $url = null): array
    {
        if (in_array($txType, [PosInterface::TX_REFUND, PosInterface::TX_STATUS, PosInterface::TX_CANCEL], true)) {
            if (!is_array($contents)) {
                throw new InvalidArgumentException(sprintf('Invalid data type provided for %s transaction!', $txType));
            }

            return $this->data = $this->sendSoapRequest($contents, $txType);
        }

        $url = $url ?: $this->getApiURL();
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
     * @phpstan-param PosInterface::TX_STATUS|PosInterface::TX_REFUND|PosInterface::TX_CANCEL $txType
     *
     * @param array<string, mixed> $contents
     * @param string               $txType
     * @param string|null          $url
     *
     * @return array<string, mixed>
     *
     * @throws SoapFault
     * @throws Throwable
     */
    protected function sendSoapRequest(array $contents, string $txType, string $url = null): array
    {
        $url = $url ?: $this->getQueryAPIUrl();

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


        $client = new SoapClient($url, $options);
        try {
            $result = $client->__soapCall($this->requestDataMapper->mapTxType($txType), ['parameters' => ['request' => $contents]]);
        } catch (Throwable $throwable) {
            $this->logger->error('soap error response', [
                'message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        if (null === $result) {
            $this->logger->error('Bankaya istek başarısız!', [
                'response' => $result,
            ]);
            throw new RuntimeException('Bankaya istek başarısız!');
        }

        return $this->serializer->decode($result, $txType);
    }

    /**
     * @phpstan-param  PosInterface::MODEL_3D_* $paymentModel
     * @phpstan-param  PosInterface::TX_*       $txType
     *
     * @param KuveytPosAccount                     $account
     * @param array<string, int|string|float|null> $order
     * @param string                               $paymentModel
     * @param string                               $txType
     * @param string                               $gatewayURL
     * @param CreditCardInterface|null             $card
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     *
     * @throws Exception
     */
    private function getCommon3DFormData(KuveytPosAccount $account, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $card = null): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData($account, $order, $paymentModel, $txType, $card);

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
        $decodedResponse = $this->send($data, $txType, $gatewayURL);

        return $this->requestDataMapper->create3DFormData($this->account, $decodedResponse['form_inputs'], $paymentModel, $txType, $decodedResponse['gateway'], $card);
    }
}

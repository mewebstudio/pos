<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use DOMDocument;
use DOMNodeList;
use Exception;
use LogicException;
use Mews\Pos\DataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

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

    /**
     * @inheritDoc
     */
    public function createXML(array $nodes, string $encoding = 'ISO-8859-1', bool $ignorePiNode = false): string
    {
        return parent::createXML(['KuveytTurkVPosMessage' => $nodes], $encoding, $ignorePiNode);
    }

    /** @return KuveytPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function send($contents, string $txType = null, string $url = null)
    {
        if (in_array($txType, [self::TX_REFUND, self::TX_STATUS, self::TX_CANCEL], true)) {
            if (!is_array($contents)) {
                throw new \LogicException("Invalid data type provided for $txType transaction!");
            }
            return $this->sendSoapRequest($contents, $txType);
        }
        $url = $url ?: $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);
        $body     = [
            'body'    => $contents,
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF-8',
            ],
        ];
        $response = $this->client->post($url, $body);
        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);

        $responseBody = $response->getBody()->getContents();
        try {
            $this->data = $this->XMLStringToArray($responseBody);
        } catch (Exception $exception) {
            if (!$this->isHTML($responseBody)) {
                throw new Exception($responseBody, $exception->getCode(), $exception);
            }

            //icinde form olan HTML response dondu
            $this->data = $responseBody;
        }

        return $this->data;
    }

    /**
     * @param array<string, mixed>                            $contents
     * @param self::TX_STATUS|self::TX_REFUND|self::TX_CANCEL $txType
     * @param string|null                                     $url
     *
     * @return array<string, mixed>
     *
     * @throws \SoapFault
     * @throws \Throwable
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


        $client = new \SoapClient($url, $options);
        try {
            $result = $client->__soapCall($this->requestDataMapper->mapTxType($txType), ['parameters' => ['request' => $contents]]);
        } catch (\Throwable $e) {
            $this->logger->log(LogLevel::ERROR, 'soap error response', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
        if (null === $result) {
            $this->logger->log(LogLevel::ERROR, 'Bankaya istek başarısız!', [
                'response' => $result,
            ]);
            throw new \RuntimeException('Bankaya istek başarısız!');
        }

        return json_decode(json_encode($result), true);
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null)
    {
        $gatewayResponse = $request->request->get('AuthenticationResponse');
        if (!is_string($gatewayResponse)) {
            throw new \LogicException('AuthenticationResponse is missing');
        }

        $gatewayResponse = urldecode($gatewayResponse);
        $gatewayResponse = $this->XMLStringToArray($gatewayResponse);

        $bankResponse   = null;
        $procReturnCode = $gatewayResponse['ResponseCode'];

        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $gatewayResponse)) {
            throw new HashMismatchException();
        }

        if ($this->responseDataMapper::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $this->logger->log(LogLevel::DEBUG, 'finishing payment');

            $contents = $this->create3DPaymentXML($gatewayResponse, $order, $txType);

            $bankResponse = $this->send($contents);
        } else {
            $this->logger->log(LogLevel::ERROR, '3d auth fail', ['proc_return_code' => $procReturnCode]);
        }

        $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponse, $bankResponse);
        $this->logger->log(LogLevel::DEBUG, 'finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request)
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request)
    {
        return $this->make3DPayPayment($request);
    }

    /**
     * Deniz bank dokumantasyonunda history sorgusu ile alakali hic bir bilgi yok
     * @inheritDoc
     */
    public function history(array $meta)
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, AbstractCreditCard $card = null): array
    {
        $gatewayUrl = $this->get3DGatewayURL();
        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->getCommon3DFormData($this->account, $order, $paymentModel, $txType, $gatewayUrl, $card);
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML(array $responseData, array $order, string $txType, AbstractCreditCard $card = null): string
    {
        $data = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $responseData);

        return $this->createXML($data);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML(array $order, AbstractCreditCard $card, string $txType)
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML(array $order)
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createStatusXML(array $order): array
    {
        return $this->requestDataMapper->createStatusRequestData($this->account, $order);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML(array $order): array
    {
        return $this->requestDataMapper->createCancelRequestData($this->account, $order);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML(array $order): array
    {
        return $this->requestDataMapper->createRefundRequestData($this->account, $order);
    }

    /**
     * @param KuveytPosAccount                     $account
     * @param array<string, int|string|float|null> $order
     * @param self::MODEL_*                        $paymentModel
     * @param self::TX_*                           $txType
     * @param string                               $gatewayURL
     * @param AbstractCreditCard|null              $card
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     *
     * @throws Exception
     */
    private function getCommon3DFormData(KuveytPosAccount $account, array $order, string $paymentModel, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        $formData     = $this->requestDataMapper->create3DEnrollmentCheckRequestData($account, $order, $paymentModel, $txType, $card);
        $xml          = $this->createXML($formData);
        $bankResponse = $this->send($xml, $txType, $gatewayURL);

        return $this->transformReceived3DFormData($bankResponse);
    }

    /**
     * Diger Gateway'lerden farkli olarak bu gateway HTML form olan bir response doner.
     * Kutupahenin islem akisina uymasi icin bu HTML form verilerini array'e donusturup, kendimiz post ediyoruz.
     *
     * @param string $response
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     */
    private function transformReceived3DFormData(string $response): array
    {
        $dom = new DOMDocument();
        $dom->loadHTML($response);

        $gatewayURL = '';
        /** @var \DOMElement $formNode */
        $formNode = $dom->getElementsByTagName('form')->item(0);
        /** @var \DOMNamedNodeMap $attributes */
        $attributes = $formNode->attributes;
        for ($i = 0; $i < $attributes->length; ++$i) {
            /** @var \DOMAttr $attribute */
            $attribute = $attributes->item($i);
            if ('action' === $attribute->name) {
                /**
                 * banka onayladiginda gatewayURL=bankanin gateway url
                 * onaylanmadiginda (hatali istek oldugunda) ise gatewayURL = istekte yer alan failURL
                 */
                $gatewayURL = $attribute->value;
                break;
            }
        }

        $els    = $dom->getElementsByTagName('input');
        $inputs = $this->builtInputsFromHTMLDoc($els);

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * html form'da gelen input degeleri array'e donusturur
     *
     * @param DOMNodeList $inputNodes
     *
     * @return array<string, string>
     */
    private function builtInputsFromHTMLDoc(DOMNodeList $inputNodes): array
    {
        $inputs = [];
        foreach ($inputNodes as $el) {
            $key   = null;
            $value = null;

            /** @var \DOMNamedNodeMap $attributes */
            $attributes = $el->attributes;
            // for each input element select name and value attribute values
            for ($i = 0; $i < $attributes->length; ++$i) {
                /** @var \DOMAttr $attribute */
                $attribute = $attributes->item($i);
                if ('name' === $attribute->name) {
                    /** @var string|null $key */
                    $key = $attribute->value;
                }

                if ('value' === $attribute->name) {
                    /** @var string|null $value */
                    $value = $attribute->value;
                }
            }

            if ($key && null !== $value && !in_array($key, ['submit', 'submitBtn'])) {
                $inputs[$key] = $value;
            }
        }

        return $inputs;
    }
}

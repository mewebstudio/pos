<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use DOMDocument;
use DOMNodeList;
use Exception;
use Mews\Pos\DataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
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

    /**
     * @return KuveytPosAccount
     */
    public function getAccount(): KuveytPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function send($contents, string $url = null)
    {
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
        } catch (Exception $e) {
            if (!$this->isHTML($responseBody)) {
                throw new Exception($responseBody);
            }
            //icinde form olan HTML response dondu
            $this->data = $responseBody;
        }

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request)
    {
        $gatewayResponse = $request->request->get('AuthenticationResponse');
        if (!is_string($gatewayResponse)) {
            throw new \LogicException('AuthenticationResponse is missing');
        }
        $gatewayResponse = urldecode($gatewayResponse);
        $gatewayResponse = $this->XMLStringToArray($gatewayResponse);
        $bankResponse    = null;
        $procReturnCode  = $gatewayResponse['ResponseCode'];

        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $gatewayResponse)) {
            throw new HashMismatchException();
        }
        if ($this->responseDataMapper::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $this->logger->log(LogLevel::DEBUG, 'finishing payment');

            $contents = $this->create3DPaymentXML($gatewayResponse);

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
    public function get3DFormData(): array
    {
        $gatewayUrl = $this->get3DGatewayURL();
        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->getCommon3DFormData($this->account, $this->order, $this->type, $gatewayUrl, $this->card);
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        $data = $this->requestDataMapper->create3DPaymentRequestData($this->account, $this->order, $this->type, $responseData);

        return $this->createXML($data);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML()
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML()
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
    public function createStatusXML()
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order)
    {
        return (object) array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? 'TRY',
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order)
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order)
    {
        return (object) $order;
    }

    /**
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $order)
    {
        return (object) $order;
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order)
    {
        return (object) $order;
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order)
    {
        return (object) $order;
    }

    /**
     * @param KuveytPosAccount        $account
     * @param                         $order
     * @param self::TX_*              $txType
     * @param string                  $gatewayURL
     * @param AbstractCreditCard|null $card
     *
     * @return array
     *
     * @throws Exception
     */
    private function getCommon3DFormData(KuveytPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        if (!$order) {
            return [];
        }

        $formData     = $this->requestDataMapper->create3DEnrollmentCheckRequestData($account, $order, $txType, $card);
        $xml          = $this->createXML($formData);
        $bankResponse = $this->send($xml, $gatewayURL);

        return $this->transformReceived3DFormData($bankResponse);
    }

    /**
     * Diger Gateway'lerden farkli olarak bu gateway HTML form olan bir response doner.
     * Kutupahenin islem akisina uymasi icin bu HTML form verilerini array'e donusturup, kendimiz post ediyoruz.
     * @param string $response
     *
     * @return array
     */
    private function transformReceived3DFormData(string $response): array
    {
        $dom = new DOMDocument();
        $dom->loadHTML($response);

        $gatewayURL = '';
        /** @var \DOMElement $formNode */
        $formNode   = $dom->getElementsByTagName('form')->item(0);
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
            'inputs'  => $inputs,
        ];
    }

    /**
     * html form'da gelen input degeleri array'e donusturur
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

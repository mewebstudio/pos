<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\AbstractRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * todo we need to update request data code base to return array instead of XML, because some providers does not use XML.
 *  for example createRefundXML() this method will be createRefund() and return array.
 *  Then it will be converted to XML in some other place if needed
 * Class AbstractGateway
 */
abstract class AbstractGateway implements PosInterface
{
    public const TX_PAY = 'pay';
    public const TX_PRE_PAY = 'pre';
    public const TX_POST_PAY = 'post';
    public const TX_CANCEL = 'cancel';
    public const TX_REFUND = 'refund';
    public const TX_STATUS = 'status';
    public const TX_HISTORY = 'history';

    public const MODEL_3D_SECURE = '3d';
    public const MODEL_3D_PAY = '3d_pay';
    public const MODEL_3D_HOST = '3d_host';
    public const MODEL_NON_SECURE = 'regular';

    protected const HASH_ALGORITHM = 'sha1';
    protected const HASH_SEPARATOR = '';

    protected $cardTypeMapping = [];

    /** @var array */
    private $config;

    /**
     * @var AbstractPosAccount
     */
    protected $account;

    /**
     * @var AbstractCreditCard
     */
    protected $card;

    /**
     * Transaction Types
     *
     * @var array
     */
    protected $types = [];

    /**
     * Transaction Type
     *
     * @var string
     */
    protected $type;

    /**
     * Recurring Order Frequency Type Mapping
     *
     * @var array
     */
    protected $recurringOrderFrequencyMapping = [];

    /**
     * @var object
     */
    protected $order;

    /**
     * Processed Response Data
     *
     * @var object
     */
    protected $response;

    /**
     * Raw Response Data
     *
     * @var object
     */
    protected $data;

    /** @var AbstractRequestDataMapper */
    protected $requestDataMapper;

    private $testMode = false;

    /**
     * AbstractGateway constructor.
     *
     * @param array                     $config
     * @param AbstractPosAccount        $account
     * @param AbstractRequestDataMapper $requestDataMapper
     */
    public function __construct(array $config, AbstractPosAccount $account, AbstractRequestDataMapper $requestDataMapper)
    {
        $this->requestDataMapper              = $requestDataMapper;
        $this->types                          = $requestDataMapper->getTxTypeMappings();
        $this->cardTypeMapping                = $requestDataMapper->getCardTypeMapping();
        $this->recurringOrderFrequencyMapping = $requestDataMapper->getRecurringOrderFrequencyMapping();

        $this->config = $config;
        $this->account = $account;
    }

    /**
     * @inheritDoc
     */
    public function prepare(array $order, string $txType, $card = null)
    {
        $this->setTxType($txType);

        switch ($txType) {
            case self::TX_PAY:
            case self::TX_PRE_PAY:
                $this->order = $this->preparePaymentOrder($order);
                break;
            case self::TX_POST_PAY:
                $this->order = $this->preparePostPaymentOrder($order);
                break;
            case self::TX_CANCEL:
                $this->order = $this->prepareCancelOrder($order);
                break;
            case self::TX_REFUND:
                $this->order = $this->prepareRefundOrder($order);
                break;
            case self::TX_STATUS:
                $this->order = $this->prepareStatusOrder($order);
                break;
            case self::TX_HISTORY:
                $this->order = $this->prepareHistoryOrder($order);
                break;
        }

        $this->card = $card;
    }

    /**
     * @return object
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return array
     */
    public function getCurrencies(): array
    {
        return $this->requestDataMapper->getCurrencyMappings();
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return AbstractPosAccount
     */
    abstract public function getAccount();

    /**
     * @return AbstractCreditCard|null
     */
    public function getCard(): ?AbstractCreditCard
    {
        return $this->card;
    }

    /**
     * @param AbstractCreditCard|null $card
     */
    public function setCard(?AbstractCreditCard $card)
    {
        $this->card = $card;
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @inheritDoc
     */
    public function createXML(array $nodes, string $encoding = 'UTF-8', bool $ignorePiNode = false): string
    {
        $rootNodeName = array_keys($nodes)[0];
        $encoder = new XmlEncoder();
        $context = [
            XmlEncoder::ROOT_NODE_NAME => $rootNodeName,
            XmlEncoder::ENCODING       => $encoding,
        ];
        if ($ignorePiNode) {
            $context[XmlEncoder::ENCODER_IGNORED_NODE_TYPES] = [
                XML_PI_NODE,
            ];
        }

        return $encoder->encode($nodes[$rootNodeName], 'xml', $context);
    }

    /**
     * Print Data
     *
     * @param $data
     *
     * @return string|null
     */
    public function printData($data): ?string
    {
        if ((is_object($data) || is_array($data)) && !count((array) $data)) {
            $data = null;
        }

        return (string) $data;
    }

    /**
     * Is success
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return isset($this->response->status) && 'approved' === $this->response->status;
    }

    /**
     * Is error
     *
     * @return bool
     */
    public function isError(): bool
    {
        return !$this->isSuccess();
    }

    /**
     * Converts XML string to object
     *
     * @param string $data
     *
     * @return object
     */
    public function XMLStringToObject($data)
    {
        $encoder = new XmlEncoder();
        $xml = $encoder->decode($data, 'xml');

        return (object) json_decode(json_encode($xml));
    }

    /**
     * @return string
     */
    public function getApiURL(): string
    {
        return $this->config['urls'][$this->getModeInWord()];
    }

    /**
     * @return string
     */
    public function get3DGatewayURL(): string
    {
        return $this->config['urls']['gateway'][$this->getModeInWord()];
    }

    /**
     * @return string|null
     */
    public function get3DHostGatewayURL(): ?string
    {
        return isset($this->config['urls']['gateway_3d_host'][$this->getModeInWord()]) ? $this->config['urls']['gateway_3d_host'][$this->getModeInWord()] : null;
    }

    /**
     * @return bool
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * @param string $txType
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function setTxType(string $txType)
    {
        if (array_key_exists($txType, $this->types)) {
            $this->type = $this->types[$txType];
        } else {
            throw new UnsupportedTransactionTypeException();
        }
    }

    /**
     * @inheritDoc
     */
    public function payment($card = null)
    {
        $request = Request::createFromGlobals();
        $this->card = $card;

        $model = $this->account->getModel();

        if (self::MODEL_NON_SECURE === $model) {
            $this->makeRegularPayment();
        } elseif (self::MODEL_3D_SECURE === $model) {
            $this->make3DPayment($request);
        } elseif (self::MODEL_3D_PAY === $model) {
            $this->make3DPayPayment($request);
        } elseif (self::MODEL_3D_HOST === $model) {
            $this->make3DHostPayment($request);
        } else {
            throw new UnsupportedPaymentModelException();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment()
    {
        $contents = '';
        if (in_array($this->type, [$this->types[self::TX_PAY], $this->types[self::TX_PRE_PAY]])) {
            $contents = $this->createRegularPaymentXML();
        } elseif ($this->types[self::TX_POST_PAY] === $this->type) {
            $contents = $this->createRegularPostXML();
        }

        $bankResponse = $this->send($contents);

        $this->response = (object) $this->mapPaymentResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function refund()
    {
        $xml = $this->createRefundXML();
        $bankResponse = $this->send($xml);

        $this->response = $this->mapRefundResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cancel()
    {
        $xml = $this->createCancelXML();
        $bankResponse = $this->send($xml);

        $this->response = $this->mapCancelResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function status()
    {
        $xml = $this->createStatusXML();

        $bankResponse = $this->send($xml);

        $this->response = $this->mapStatusResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function history(array $meta)
    {
        $xml = $this->createHistoryXML($meta);

        $bankResponse = $this->send($xml);

        $this->response = $this->mapHistoryResponse($bankResponse);

        return $this;
    }

    /**
     * @param bool $testMode
     *
     * @return $this
     */
    public function setTestMode(bool $testMode): self
    {
        $this->testMode = $testMode;
        if (isset($this->requestDataMapper)) {
            //todo remove if check after all gateways has requestDataMapper
            $this->requestDataMapper->setTestMode($testMode);
        }

        return $this;
    }

    /**
     * @param string $period
     *
     * @return string
     */
    public function mapRecurringFrequency(string $period): string
    {
        return $this->recurringOrderFrequencyMapping[$period] ?? $period;
    }

    /**
     * @return array
     */
    public function getCardTypeMapping(): array
    {
        return $this->cardTypeMapping;
    }

    /**
     * Create Regular Payment XML
     *
     * @return string
     */
    abstract public function createRegularPaymentXML();

    /**
     * Create Regular Payment Post XML
     *
     * @return string
     */
    abstract public function createRegularPostXML();

    /**
     * Creates XML string for history inquiry
     *
     * @param array $customQueryData
     *
     * @return string
     */
    abstract public function createHistoryXML($customQueryData);

    /**
     * Creates XML string for order status inquiry
     * @return mixed
     */
    abstract public function createStatusXML();

    /**
     * Creates XML string for order cancel operation
     * @return string
     */
    abstract public function createCancelXML();

    /**
     * Creates XML string for order refund operation
     * @return mixed
     */
    abstract public function createRefundXML();

    /**
     * Creates 3D Payment XML
     *
     * @param $responseData
     *
     * @return string|array
     */
    abstract public function create3DPaymentXML($responseData);

    /**
     * returns form data, key values, necessary for 3D payment
     *
     * @return array
     */
    abstract public function get3DFormData(): array;

    /**
     * prepares order for payment request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function preparePaymentOrder(array $order);

    /**
     * prepares order for TX_POST_PAY type request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function preparePostPaymentOrder(array $order);

    /**
     * prepares order for order status request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function prepareStatusOrder(array $order);

    /**
     * prepares order for history request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function prepareHistoryOrder(array $order);

    /**
     * prepares order for cancel request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function prepareCancelOrder(array $order);

    /**
     * prepares order for refund request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function prepareRefundOrder(array $order);

    /**
     * @param array        $raw3DAuthResponseData  response from 3D authentication
     * @param object|array $rawPaymentResponseData
     *
     * @return object
     */
    abstract protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData);

    /**
     * @param array $raw3DAuthResponseData response from 3D authentication
     *
     * @return object
     */
    abstract protected function map3DPayResponseData($raw3DAuthResponseData);

    /**
     * Processes regular payment response data
     *
     * @param object|array $responseData
     *
     * @return array
     */
    abstract protected function mapPaymentResponse($responseData): array;

    /**
     * @param $rawResponseData
     *
     * @return object
     */
    abstract protected function mapRefundResponse($rawResponseData);

    /**
     * @param $rawResponseData
     *
     * @return object
     */
    abstract protected function mapCancelResponse($rawResponseData);

    /**
     * @param object $rawResponseData
     *
     * @return object
     */
    abstract protected function mapStatusResponse($rawResponseData);

    /**
     * @param object $rawResponseData
     *
     * @return mixed
     */
    abstract protected function mapHistoryResponse($rawResponseData);

    /**
     * Returns payment default response data
     *
     * @return array
     */
    protected function getDefaultPaymentResponse(): array
    {
        return [
            'id'               => null,
            'order_id'         => null,
            'trans_id'         => null,
            'transaction_type' => $this->type,
            'transaction'      => $this->type,
            'auth_code'        => null,
            'host_ref_num'     => null,
            'proc_return_code' => null,
            'code'             => null,
            'status'           => 'declined',
            'status_detail'    => null,
            'error_code'       => null,
            'error_message'    => null,
            'response'         => null,
            'all'              => null,
        ];
    }

    /**
     * bank returns error messages for specified language value
     * usually accepted values are tr,en
     * @return string
     */
    protected function getLang(): string
    {
        if ($this->order && isset($this->order->lang)) {
            return $this->order->lang;
        }

        return $this->account->getLang();
    }

    /**
     * @param string $str
     *
     * @return bool
     */
    protected function isHTML($str): bool
    {
        return $str !== strip_tags($str);
    }

    /**
     * @param string $str
     *
     * @return string
     */
    protected function hashString(string $str): string
    {
        return base64_encode(hash(static::HASH_ALGORITHM, $str, true));
    }

    /**
     * if 2 arrays has common keys, then non-null value preferred,
     * if both arrays has the non-null values for the same key then value of $arr2 is preferred.
     * @param array $arr1
     * @param array $arr2
     *
     * @return array
     */
    protected function mergeArraysPreferNonNullValues(array $arr1, array $arr2): array
    {
        $resultArray = array_diff_key($arr1, $arr2) + array_diff_key($arr2, $arr1);
        $commonArrayKeys = array_keys(array_intersect_key($arr1, $arr2));
        foreach ($commonArrayKeys as $key) {
            $resultArray[$key] = $arr2[$key] ?: $arr1[$key];
        }

        return $resultArray;
    }

    /**
     * Converts XML string to array
     *
     * @param string $data
     * @param array  $context
     *
     * @return array
     */
    protected function XMLStringToArray(string $data, array $context = []): array
    {
        $encoder = new XmlEncoder();

        return $encoder->decode($data, 'xml', $context);
    }

    /**
     * bankadan gelen response'da bos string degerler var.
     * bu metod ile bos string'leri null deger olarak degistiriyoruz
     *
     * @param string|object|array $data
     *
     * @return string|array
     */
    protected function emptyStringsToNull($data)
    {
        $result = [];
        if (is_string($data)) {
            $result = '' === $data ? null : $data;
        } elseif (is_numeric($data)) {
            $result = $data;
        } elseif (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                $result[$key] = self::emptyStringsToNull($value);
            }
        }

        return $result;
    }

    /**
     * return values are used as a key in config file
     * @return string
     */
    private function getModeInWord(): string
    {
        return !$this->isTestMode() ? 'production' : 'test';
    }
}

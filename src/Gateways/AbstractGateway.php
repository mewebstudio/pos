<?php


namespace Mews\Pos\Gateways;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * Class AbstractGateway
 */
abstract class AbstractGateway implements PosInterface
{

    const TX_PAY = 'pay';
    const TX_PRE_PAY = 'pre';
    const TX_POST_PAY = 'post';
    const TX_CANCEL = 'cancel';
    const TX_REFUND = 'refund';
    const TX_STATUS = 'status';
    const TX_HISTORY = 'history';

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
     * Currency mapping
     *
     * @var array
     */
    protected $currencies;

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

    private $testMode = false;

    /**
     * AbstractGateway constructor.
     *
     * @param                    $config
     * @param AbstractPosAccount $account
     * @param array              $currencies
     */
    public function __construct($config, $account, ?array $currencies)
    {
        $this->config = $config;
        $this->account = $account;

        if (count($currencies) > 0) {
            $this->currencies = $currencies;
        }
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
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return array
     */
    public function getCurrencies()
    {
        return $this->currencies;
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
     * @return AbstractCreditCard
     */
    abstract public function getCard();

    /**
     * @param AbstractCreditCard|null $card
     */
    abstract public function setCard($card);

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Create XML DOM Document
     *
     * @param array  $nodes
     * @param string $encoding
     *
     * @return string the XML, or false if an error occurred.
     */
    public function createXML(array $nodes, $encoding = 'UTF-8')
    {
        $rootNodeName = array_keys($nodes)[0];
        $encoder = new XmlEncoder();

        return $encoder->encode($nodes[$rootNodeName], 'xml', [
            XmlEncoder::ROOT_NODE_NAME => $rootNodeName,
            XmlEncoder::ENCODING       => $encoding,
        ]);
    }

    /**
     * Print Data
     *
     * @param $data
     *
     * @return null|string
     */
    public function printData($data)
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
    public function isSuccess()
    {
        if (isset($this->response) && 'approved' === $this->response->status) {
            return true;
        }

        return false;
    }

    /**
     * Is error
     *
     * @return bool
     */
    public function isError()
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
    public function getApiURL()
    {
        return $this->config['urls'][$this->getModeInWord()];
    }

    /**
     * @return string
     */
    public function get3DGatewayURL()
    {
        return $this->config['urls']['gateway'][$this->getModeInWord()];
    }

    /**
     * @return string
     */
    public function get3DHostGatewayURL()
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
        $this->card = $card;

        $model = $this->account->getModel();

        if ('regular' === $model) {
            $this->makeRegularPayment();
        } elseif ('3d' === $model) {
            $this->make3DPayment();
        } elseif ('3d_pay' === $model) {
            $this->make3DPayPayment();
        } elseif ('3d_host' === $model) {
            $this->make3DHostPayment();
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

        $this->send($contents);

        $this->response = (object) $this->mapPaymentResponse($this->data);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function refund()
    {
        $xml = $this->createRefundXML();
        $this->send($xml);

        $this->response = $this->mapRefundResponse($this->data);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cancel()
    {
        $xml = $this->createCancelXML();
        $this->send($xml);

        $this->response = $this->mapCancelResponse($this->data);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function status()
    {
        $xml = $this->createStatusXML();

        $this->send($xml);

        $this->response = $this->mapStatusResponse($this->data);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function history(array $meta)
    {
        $xml = $this->createHistoryXML($meta);

        $this->send($xml);

        $this->response = $this->mapHistoryResponse($this->data);

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

        return $this;
    }

    /**
     * @param string $currency TRY, USD
     *
     * @return string
     */
    public function mapCurrency(string $currency): string
    {
        return isset($this->currencies[$currency]) ? $this->currencies[$currency] : $currency;
    }

    /**
     * @param string $period
     *
     * @return string
     */
    public function mapRecurringFrequency(string $period): string
    {
        return isset($this->recurringOrderFrequencyMapping[$period]) ? $this->recurringOrderFrequencyMapping[$period] : $period;
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
     * @return string
     */
    abstract public function create3DPaymentXML($responseData);

    /**
     * returns form data, key values, necessary for 3D payment
     *
     * @return array
     */
    abstract public function get3DFormData();

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
     * @param array  $raw3DAuthResponseData response from 3D authentication
     * @param object $rawPaymentResponseData
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
     * @param object $responseData
     *
     * @return array
     */
    abstract protected function mapPaymentResponse($responseData);

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
    protected function getDefaultPaymentResponse()
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
            'all'              => null,
        ];
    }

    /**
     * bank returns error messages for specified language value
     * usually accepted values are tr,en
     * @return string
     */
    protected function getLang()
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
    protected function isHTML($str)
    {
        return $str !== strip_tags($str);
    }

    /**
     * return values are used as a key in config file
     * @return string
     */
    private function getModeInWord()
    {
        return !$this->isTestMode() ? 'production' : 'test';
    }
}

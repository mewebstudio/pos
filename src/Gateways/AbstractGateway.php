<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Mews\Pos\Client\HttpClient;
use Mews\Pos\DataMapper\AbstractRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\NonPaymentResponseMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\PaymentResponseMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * todo we need to update request data code base to return array instead of XML, because some providers does not use
 * XML. for example createRefundXML() this method will be createRefund() and return array. Then it will be converted to
 * XML in some other place if needed Class AbstractGateway
 */
abstract class AbstractGateway implements PosInterface
{

    public const LANG_TR = 'tr';
    public const LANG_EN = 'en';

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

    /** @var array */
    private $config;

    /** @var AbstractPosAccount */
    protected $account;

    /** @var AbstractCreditCard|null */
    protected $card;

    /**
     * Transaction Type
     *
     * @var self::TX_*
     */
    protected $type;

    /**
     * @var object|null
     */
    protected $order;

    /**
     * Processed Response Data
     *
     * @var array|null
     */
    protected $response;

    /**
     * Raw Response Data From Bank
     *
     * @var mixed
     */
    protected $data;

    /** @var HttpClient */
    protected $client;

    /** @var AbstractRequestDataMapper */
    protected $requestDataMapper;

    /** @var PaymentResponseMapperInterface&NonPaymentResponseMapperInterface */
    protected $responseDataMapper;

    /** @var LoggerInterface */
    protected $logger;

    /** @var bool */
    private $testMode = false;


    public function __construct(
        array                          $config,
        AbstractPosAccount             $account,
        AbstractRequestDataMapper      $requestDataMapper,
        PaymentResponseMapperInterface $responseDataMapper,
        HttpClient                     $client,
        LoggerInterface                $logger
    ) {
        $this->requestDataMapper  = $requestDataMapper;
        $this->responseDataMapper = $responseDataMapper;

        $this->config  = $config;
        $this->account = $account;
        $this->client  = $client;
        $this->logger  = $logger;
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
        $this->logger->log(LogLevel::DEBUG, 'gateway prepare - order is prepared', [$this->order]);

        $this->card = $card;
    }

    /**
     * @return array|null
     */
    public function getResponse(): ?array
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
     * @return object
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
        $encoder      = new XmlEncoder();
        $context      = [
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
     * Is success
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return isset($this->response['status']) && $this->responseDataMapper::TX_APPROVED === $this->response['status'];
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
     * @param self::TX_* $txType
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function setTxType(string $txType)
    {
        $this->requestDataMapper->mapTxType($txType);

        $this->type = $txType;

        $this->logger->log(LogLevel::DEBUG, 'set transaction type', [$txType]);
    }

    /**
     * @inheritDoc
     */
    public function payment($card = null)
    {
        $request    = Request::createFromGlobals();
        $this->card = $card;

        $model = $this->account->getModel();

        $this->logger->log(LogLevel::DEBUG, 'payment called', [
            'card_provided' => !!$this->card,
            'model'         => $model,
        ]);
        if (self::MODEL_NON_SECURE === $model) {
            $this->makeRegularPayment();
        } elseif (self::MODEL_3D_SECURE === $model) {
            $this->make3DPayment($request);
        } elseif (self::MODEL_3D_PAY === $model) {
            $this->make3DPayPayment($request);
        } elseif (self::MODEL_3D_HOST === $model) {
            $this->make3DHostPayment($request);
        } else {
            $this->logger->log(LogLevel::ERROR, 'unsupported payment model', ['model' => $model]);
            throw new UnsupportedPaymentModelException();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment()
    {
        $this->logger->log(LogLevel::DEBUG, 'making payment', [
            'model'   => $this->account->getModel(),
            'tx_type' => $this->type,
        ]);
        $contents = '';
        if (in_array($this->type, [self::TX_PAY, self::TX_PRE_PAY])) {
            $contents = $this->createRegularPaymentXML();
        } elseif (self::TX_POST_PAY === $this->type) {
            $contents = $this->createRegularPostXML();
        }

        $bankResponse = $this->send($contents);

        $this->response = $this->responseDataMapper->mapPaymentResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function refund()
    {
        $xml          = $this->createRefundXML();
        $bankResponse = $this->send($xml);

        $this->response = $this->responseDataMapper->mapRefundResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cancel()
    {
        $xml          = $this->createCancelXML();
        $bankResponse = $this->send($xml);

        $this->response = $this->responseDataMapper->mapCancelResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function status()
    {
        $xml = $this->createStatusXML();

        $bankResponse = $this->send($xml);

        $this->response = $this->responseDataMapper->mapStatusResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function history(array $meta)
    {
        $xml = $this->createHistoryXML($meta);

        $bankResponse = $this->send($xml);

        $this->response = $this->responseDataMapper->mapHistoryResponse($bankResponse);

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
        $this->requestDataMapper->setTestMode($testMode);
        $this->logger->log(LogLevel::DEBUG, 'switching mode', ['mode' => $this->getModeInWord()]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getCardTypeMapping(): array
    {
        return $this->requestDataMapper->getCardTypeMapping();
    }

    /**
     * @return string[]
     */
    public function getLanguages(): array
    {
        return [self::LANG_TR, self::LANG_EN];
    }

    /**
     * Create Regular Payment XML
     *
     * @return string|array
     */
    abstract public function createRegularPaymentXML();

    /**
     * Create Regular Payment Post XML
     *
     * @return string|array
     */
    abstract public function createRegularPostXML();

    /**
     * Creates XML string for history inquiry
     *
     * @param array $customQueryData
     *
     * @return array|string
     */
    abstract public function createHistoryXML($customQueryData);

    /**
     * Creates XML string for order status inquiry
     * @return array|string
     */
    abstract public function createStatusXML();

    /**
     * Creates XML string for order cancel operation
     * @return array|string
     */
    abstract public function createCancelXML();

    /**
     * Creates XML string for order refund operation
     * @return array|string
     */
    abstract public function createRefundXML();

    /**
     * Creates 3D Payment XML
     *
     * @param array<string, string> $responseData
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
     * @param string $str
     *
     * @return bool
     */
    protected function isHTML($str): bool
    {
        return $str !== strip_tags($str);
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
     * return values are used as a key in config file
     * @return string
     */
    private function getModeInWord(): string
    {
        return !$this->isTestMode() ? 'production' : 'test';
    }
}

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
    /** @var array */
    protected $config;

    /** @var AbstractPosAccount */
    protected $account;

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
    )
    {
        $this->requestDataMapper  = $requestDataMapper;
        $this->responseDataMapper = $responseDataMapper;

        $this->config  = $config;
        $this->account = $account;
        $this->client  = $client;
        $this->logger  = $logger;
    }

    /**
     * @return array|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }

    /**
     * @return non-empty-array<string, string>
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
     * @param self::TX_* $txType
     *
     * @return string
     */
    public function getApiURL(string $txType = null): string
    {
        return $this->config['gateway_endpoints']['payment_api'];
    }

    /**
     * @return string
     */
    public function get3DGatewayURL(): string
    {
        return $this->config['gateway_endpoints']['gateway_3d'];
    }

    /**
     * @return string
     */
    public function get3DHostGatewayURL(): string
    {
        return $this->config['gateway_endpoints']['gateway_3d_host'] ?? $this->get3DGatewayURL();
    }

    /**
     * @return string
     */
    public function getQueryAPIUrl(): string
    {
        return $this->config['gateway_endpoints']['query_api'] ?? $this->getApiURL();
    }

    /**
     * @return bool
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * @inheritDoc
     */
    public function payment(string $paymentModel, array $order, string $txType, $card = null)
    {
        $request = Request::createFromGlobals();

        $this->logger->log(LogLevel::DEBUG, 'payment called', [
            'card_provided' => (bool) $card,
            'model'         => $paymentModel,
        ]);
        if (PosInterface::MODEL_NON_SECURE === $paymentModel) {
            if (!$card instanceof AbstractCreditCard) {
                throw new \LogicException('Kredi kartı veya sipariş bilgileri eksik!');
            }
            $this->makeRegularPayment($order, $card, $txType);
        } elseif (PosInterface::MODEL_3D_SECURE === $paymentModel) {
            if (PosInterface::TX_POST_PAY === $txType) {
                throw new \LogicException('Bu işlem için $paymentModel=MODEL_NON_SECURE kullanınız!');
            }
            $this->make3DPayment($request, $order, $txType, $card);
        } elseif (PosInterface::MODEL_3D_PAY === $paymentModel || PosInterface::MODEL_3D_PAY_HOSTING === $paymentModel) {
            $this->make3DPayPayment($request);
        } elseif (PosInterface::MODEL_3D_HOST === $paymentModel) {
            $this->make3DHostPayment($request);
        } else {
            $this->logger->log(LogLevel::ERROR, 'unsupported payment model', ['model' => $paymentModel]);
            throw new UnsupportedPaymentModelException();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment(array $order, AbstractCreditCard $card, string $txType)
    {
        $this->logger->log(LogLevel::DEBUG, 'making payment', [
            'model'   => PosInterface::MODEL_NON_SECURE,
            'tx_type' => $txType,
        ]);
        $contents = '';
        if (in_array($txType, [PosInterface::TX_PAY, PosInterface::TX_PRE_PAY], true)) {
            $contents = $this->createRegularPaymentXML($order, $card, $txType);
        } elseif (PosInterface::TX_POST_PAY === $txType) {
            $contents = $this->createRegularPostXML($order);
        }

        $bankResponse = $this->send($contents, $txType);

        $this->response = $this->responseDataMapper->mapPaymentResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function refund(array $order)
    {
        $xml          = $this->createRefundXML($order);
        $bankResponse = $this->send($xml, PosInterface::TX_REFUND);

        $this->response = $this->responseDataMapper->mapRefundResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cancel(array $order)
    {
        $xml          = $this->createCancelXML($order);
        $bankResponse = $this->send($xml, PosInterface::TX_CANCEL);

        $this->response = $this->responseDataMapper->mapCancelResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function status(array $order)
    {
        $xml = $this->createStatusXML($order);

        $bankResponse = $this->send($xml, PosInterface::TX_STATUS, $this->getQueryAPIUrl());
        if (!is_array($bankResponse)) {
            throw new \RuntimeException('Status isteği başarısız');
        }

        $this->response = $this->responseDataMapper->mapStatusResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function history(array $meta)
    {
        $xml = $this->createHistoryXML($meta);

        $bankResponse = $this->send($xml, PosInterface::TX_HISTORY);

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
        return [PosInterface::LANG_TR, PosInterface::LANG_EN];
    }

    /**
     * Create Regular Payment XML
     *
     * @param array<string, mixed>                          $order
     * @param AbstractCreditCard                            $card
     * @param PosInterface::TX_PAY|PosInterface::TX_PRE_PAY $txType
     *
     * @return string|array<string, mixed>
     */
    abstract public function createRegularPaymentXML(array $order, AbstractCreditCard $card, string $txType);

    /**
     * Create Regular Payment Post XML
     *
     * @param array<string, mixed> $order
     *
     * @return string|array<string, mixed>
     */
    abstract public function createRegularPostXML(array $order);

    /**
     * Creates XML string for history inquiry
     *
     * @param array<string, mixed> $customQueryData
     *
     * @return array<string, mixed>|string
     */
    abstract public function createHistoryXML(array $customQueryData);

    /**
     * Creates XML string for order status inquiry
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>|string
     */
    abstract public function createStatusXML(array $order);

    /**
     * Creates XML string for order cancel operation
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>|string
     */
    abstract public function createCancelXML(array $order);

    /**
     * Creates XML string for order refund operation
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>|string
     */
    abstract public function createRefundXML(array $order);

    /**
     * Creates 3D Payment XML
     *
     * @param array<string, mixed>                          $responseData
     * @param array<string, mixed>                          $order
     * @param PosInterface::TX_PAY|PosInterface::TX_PRE_PAY $txType
     * @param AbstractCreditCard                            $card
     *
     * @return string|array<string, mixed>
     */
    abstract public function create3DPaymentXML(array $responseData, array $order, string $txType, AbstractCreditCard $card = null);

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
    protected function getModeInWord(): string
    {
        return $this->isTestMode() ? 'test' : 'production';
    }
}

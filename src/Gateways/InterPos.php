<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\InterPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\InterPosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Deniz bankin desteklidigi Gateway
 * Class InterPos
 */
class InterPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'InterPos';

    /** @var InterPosAccount */
    protected $account;

    /** @var InterPosRequestDataMapper */
    protected $requestDataMapper;

    /** @var InterPosResponseDataMapper */
    protected $responseDataMapper;

    /** @return InterPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function send($contents, string $txType = null, ?string $url = null): array
    {
        $url = $url ?: $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);
        $payload = is_array($contents) ? ['form_params' => $contents] : ['body' => $contents];
        $response = $this->client->post($url, $payload);
        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);

        //genelde ;; delimiter kullanilmis, ama bazen arasinda ;;; boyle delimiter de var.
        $resultValues = preg_split('/(;;;|;;)/', $response->getBody()->getContents());
        $result       = [];
        foreach ($resultValues as $val) {
            [$key, $value] = explode('=', $val);
            $result[$key] = $value;
        }

        return $this->data = $result;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null)
    {
        $bankResponse    = null;
        $request         = $request->request;
        /** @var array{MD: string, PayerTxnId: string, Eci: string, PayerAuthenticationCode: string} $gatewayResponse */
        $gatewayResponse = $request->all();

        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $gatewayResponse)) {
            throw new HashMismatchException();
        }

        if ('1' !== $request->get('3DStatus')) {
            $this->logger->log(LogLevel::ERROR, '3d auth fail', ['md_status' => $request->get('3DStatus')]);
            /**
             * TODO hata durumu ele alinmasi gerekiyor
             */
        } else {
            $this->logger->log(LogLevel::DEBUG, 'finishing payment');
            $contents     = $this->create3DPaymentXML($gatewayResponse, $order, $txType);
            $bankResponse = $this->send($contents);
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
        $this->response  = $this->responseDataMapper->map3DPayResponseData($request->request->all());

        return $this;
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
        $gatewayUrl = $this->get3DHostGatewayURL();

        if (self::MODEL_3D_SECURE === $paymentModel || self::MODEL_3D_PAY === $paymentModel) {
            $gatewayUrl = $this->get3DGatewayURL();
        }

        $preparedOrder = $this->preparePaymentOrder($order);

        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $preparedOrder, $paymentModel, $txType, $gatewayUrl, $card);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML(array $order, AbstractCreditCard $card, string $txType): array
    {
        $preparedOrder = $this->preparePaymentOrder($order);

        return $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $preparedOrder, $txType, $card);
    }

    /**
     * @inheritDoc
     * @return array{TxnType: string, SecureType: string, OrderId: null, orgOrderId: mixed, PurchAmount: mixed, Currency: string, MOTO: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createRegularPostXML(array $order): array
    {
        $preparedOrder = $this->preparePostPaymentOrder($order);

        return $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $preparedOrder);
    }

    /**
     * @inheritDoc
     *
     * @param array{MD: string, PayerTxnId: string, Eci: string, PayerAuthenticationCode: string} $responseData
     */
    public function create3DPaymentXML(array $responseData, array $order, string $txType, AbstractCreditCard $card = null): array
    {
        $preparedOrder = $this->preparePaymentOrder($order);

        return $this->requestDataMapper->create3DPaymentRequestData($this->account, $preparedOrder, $txType, $responseData);
    }

    /**
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData): array
    {
        $preparedOrder = $this->prepareHistoryOrder($customQueryData);

        return $this->requestDataMapper->createHistoryRequestData($this->account, $preparedOrder, $customQueryData);
    }

    /**
     * @inheritDoc
     * @return array{OrderId: null, orgOrderId: string, TxnType: string, SecureType: string, Lang: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createStatusXML(array $order): array
    {
        $preparedOrder = $this->prepareStatusOrder($order);

        return $this->requestDataMapper->createStatusRequestData($this->account, $preparedOrder);
    }

    /**
     * @inheritDoc
     * @return array{OrderId: null, orgOrderId: string, TxnType: string, SecureType: string, Lang: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createCancelXML(array $order): array
    {
        $preparedOrder = $this->prepareCancelOrder($order);

        return $this->requestDataMapper->createCancelRequestData($this->account, $preparedOrder);
    }

    /**
     * @inheritDoc
     *
     * @return array{OrderId: null, orgOrderId: string, PurchAmount: string, TxnType: string, SecureType: string, Lang: string, MOTO: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createRefundXML(array $order): array
    {
        $preparedOrder = $this->prepareRefundOrder($order);

        return $this->requestDataMapper->createRefundRequestData($this->account, $preparedOrder);
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
        return (object) [
            'id'       => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'] ?? 'TRY',
        ];
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
}

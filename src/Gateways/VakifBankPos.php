<?php


namespace Mews\Pos\Gateways;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Entity\Card\CreditCardVakifBank;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Class VakifBankPos
 */
class VakifBankPos extends AbstractGateway
{
    /**
     * @var VakifBankAccount
     */
    protected $account;

    /**
     * @var CreditCardVakifBank
     */
    protected $card;

    /**
     * Response Codes
     *
     * @var array
     */
    protected $codes = [
        '0000' => 'approved',
        // TODO map other codes
    ];

    /**
     * Transaction Types
     *
     * @var array
     */
    protected $types = [
        self::TX_PAY      => 'Sale',
        self::TX_PRE_PAY  => 'Auth',
        self::TX_POST_PAY => 'Capture',
        self::TX_CANCEL   => 'Cancel',
        self::TX_REFUND   => 'Refund',
        self::TX_HISTORY  => 'TxnHistory',
        self::TX_STATUS   => 'OrderInquiry',
    ];

    /**
     * currency mapping
     *
     * @var array
     */
    protected $currencies = [
        'TRY' => 949,
        'USD' => 840,
        'EUR' => 978,
        'GBP' => 826,
        'JPY' => 392,
        'RUB' => 643,
    ];

    /**
     * @inheritDoc
     *
     * @param VakifBankAccount $account
     */
    public function __construct($config, $account, array $currencies)
    {
        parent::__construct($config, $account, $currencies);
    }

    /**
     * @return VakifBankAccount
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @return CreditCardVakifBank
     */
    public function getCard()
    {
        return $this->card;
    }

    /**
     * @param CreditCardVakifBank|null $card
     */
    public function setCard($card)
    {
        $this->card = $card;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment()
    {
        $request = Request::createFromGlobals()->request;

        // 3D authorization failed
        if ('Y' !== $request->get('Status') || 'A' !== $request->get('Status')) {
            $this->response = $this->map3DPaymentData($request->all(), (object) []);

            return $this;
        }

        if ('A' === $request->get('Status')) {
            // TODO Half 3D Secure
            $this->response = $this->map3DPaymentData($request->all(), (object) []);

            return $this;
        }

        $contents = $this->create3DPaymentXML($request->all());
        $this->send($contents);

        $this->response = $this->map3DPaymentData($request->all(), $this->data);

        return $this;
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function make3DPayPayment()
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function make3DHostPayment()
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function history(array $meta)
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * returns form data needed for 3d model
     *
     * @return array
     *
     * @throws Exception|GuzzleException
     */
    public function get3DFormData()
    {
        if (!$this->card || !$this->order) {
            return [];
        }

        $data = $this->sendEnrollmentRequest();
        /**
         * Status values:
         * Y:Kart 3-D Secure programına dâhil
         * N:Kart 3-D Secure programına dâhil değil
         * U:İşlem gerçekleştirilemiyor
         * E:Hata durumu
         */
        if ('E' === $data->Message->VERes->Status) {
            throw new Exception($data->ErrorMessage, $data->ErrorCode);
        }
        if ('N' === $data->Message->VERes->Status) {
            // todo devam half secure olarak devam et yada satisi iptal et.
            throw new Exception('Kart 3-D Secure programına dâhil değil');
        }
        if ('U' === $data->Message->VERes->Status) {
            throw new Exception('İşlem gerçekleştirilemiyor');
        }

        $inputs = [
            'PaReq'   => $data->Message->VERes->PaReq,
            'TermUrl' => $data->Message->VERes->TermUrl,
            'MD'      => $data->Message->VERes->MD,
        ];

        return [
            'gateway' => $data->Message->VERes->ACSUrl,
            'inputs'  => $inputs,
        ];
    }

    /**
     * Müşteriden kredi kartı bilgilerini aldıktan sonra GET 7/24 MPI’a kart “Kredi Kartı Kayıt Durumu”nun
     * (Enrollment Status) sorulması, yani kart 3-D Secure programına dâhil mi yoksa değil mi sorgusu
     *
     * @return object
     *
     * @throws GuzzleException
     */
    public function sendEnrollmentRequest()
    {
        $requestData = $this->create3DEnrollmentCheckData();

        return $this->send($requestData, $this->get3DGatewayURL())->data;
    }

    /**
     * Amount Formatter
     *
     * @param float $amount
     *
     * @return string ex: 2100.00
     */
    public static function amountFormat(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * @inheritDoc
     */
    public function createXML(array $data, $encoding = 'UTF-8'): string
    {
        return parent::createXML($data, $encoding);
    }

    /**
     * @inheritDoc
     */
    public function send($postData, $url = null)
    {
        $client = new Client();
        $url = $url ? $url : $this->getApiURL();

        $isXML = is_string($postData);
        $body = $isXML ? ['body' => $postData] : ['form_params' => $postData];

        $response = $client->request('POST', $url, $body);

        $contents = $response->getBody()->getContents();

        try {
            $this->data = $this->XMLStringToObject($contents);
        } catch (NotEncodableValueException $e) {
            if ($this->isHTML($contents)) {
                // if something wrong server responds with HTML content
                throw new Exception($contents);
            }
            $this->data = (object) json_decode($contents);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML()
    {
        $requestData = [
            'MerchantId'              => $this->account->getClientId(),
            'Password'                => $this->account->getPassword(),
            'TerminalNo'              => $this->account->getTerminalId(),
            'TransactionType'         => $this->type,
            'OrderId'                 => $this->order->id,
            'CurrencyAmount'          => $this->order->amount,
            'CurrencyCode'            => $this->order->currency,
            'ClientIp'                => $this->order->ip,
            'TransactionDeviceSource' => 0,
            'Pan'                     => $this->card->getNumber(),
            'Expiry'                  => $this->card->getExpirationDate(),
            'Cvv'                     => $this->card->getCvv(),
        ];

        return $this->createXML(['VposRequest' => $requestData]);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML()
    {
        $requestData = [
            'MerchantId'             => $this->account->getClientId(),
            'Password'               => $this->account->getPassword(),
            'TerminalNo'             => $this->account->getTerminalId(),
            'TransactionType'        => $this->type,
            'ReferenceTransactionId' => $this->order->id,
            'CurrencyAmount'         => $this->order->amount,
            'CurrencyCode'           => $this->order->currency,
            'ClientIp'               => $this->order->ip,
        ];

        return $this->createXML(['VposRequest' => $requestData]);
    }

    /**
     * @return array
     */
    public function create3DEnrollmentCheckData()
    {
        $requestData = [
            'MerchantId'                => $this->account->getClientId(),
            'MerchantPassword'          => $this->account->getPassword(),
            'MerchantType'              => $this->account->getMerchantType(),
            'PurchaseAmount'            => $this->order->amount,
            'VerifyEnrollmentRequestId' => $this->order->rand,
            'Currency'                  => $this->order->currency,
            'SuccessUrl'                => $this->order->success_url,
            'FailureUrl'                => $this->order->fail_url,
            'InstallmentCount'          => $this->order->installment,
            'Pan'                       => $this->card->getNumber(),
            'ExpiryDate'                => $this->card->getExpirationDate(),
            'BrandName'                 => $this->card->getCardCode(),
            'IsRecurring'               => 'false',
        ];
        if ($this->order->installment) {
            $requestData['InstallmentCount'] = $this->order->installment;
        }
        if (isset($this->order->extraData)) {
            $requestData['SessionInfo'] = $this->order->extraData;
        }
        if ($this->account->isSubBranch()) {
            $requestData['SubMerchantId'] = $this->account->getSubMerchantId();
        }
        if (isset($this->order->recurringFrequency)) {
            $requestData['IsRecurring'] = 'true';
            // Periyodik İşlem Frekansı
            $requestData['RecurringFrequency'] = $this->order->recurringFrequency;
            //Day|Month|Year
            $requestData['RecurringFrequencyType'] = $this->order->recurringFrequencyType;
            //recurring işlemin toplamda kaç kere tekrar edeceği bilgisini içerir
            $requestData['RecurringInstallmentCount'] = $this->order->recurringInstallmentCount;
            if (isset($this->order->recurringEndDate)) {
                //YYYYMMDD
                $requestData['RecurringEndDate'] = $this->order->recurringEndDate;
            }
        }

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        $requestData = [
            'MerchantId'              => $this->account->getClientId(),
            'Password'                => $this->account->getPassword(),
            'TerminalNo'              => $this->account->getTerminalId(),
            'TransactionType'         => $this->type,
            'TransactionId'           => $this->order->rand,
            'NumberOfInstallments'    => $this->order->installment,
            'CardHoldersName'         => $this->card->getHolderName(),
            'Cvv'                     => $this->card->getCvv(),
            'ECI'                     => $responseData['Eci'],
            'CAVV'                    => $responseData['Cavv'],
            'MpiTransactionId'        => $responseData['VerifyEnrollmentRequestId'],
            'OrderId'                 => $this->order->id,
            'OrderDescription'        => isset($this->order->description) ? $this->order->description : null,
            'ClientIp'                => $this->order->ip,
            'TransactionDeviceSource' => 0, // ECommerce
        ];

        if ($this->order->installment) {
            $requestData['NumberOfInstallments'] = $this->order->installment;
        }

        return $this->createXML(['VposRequest' => $requestData]);
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function createStatusXML()
    {
        $requestData = [];

        return $this->createXML($requestData);
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {
        $requestData = [];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {
        $requestData = [
            'MerchantId'             => $this->account->getClientId(),
            'Password'               => $this->account->getPassword(),
            'TransactionType'        => $this->type,
            'ReferenceTransactionId' => $this->order->id,
            'ClientIp'               => $this->order->ip,
            'CurrencyAmount'         => $this->order->amount,
        ];

        return $this->createXML(['VposRequest' => $requestData]);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {
        $requestData = [
            'MerchantId'             => $this->account->getClientId(),
            'Password'               => $this->account->getPassword(),
            'TransactionType'        => $this->type,
            'ReferenceTransactionId' => $this->order->id,
            'ClientIp'               => $this->order->ip,
        ];

        return $this->createXML(['VposRequest' => $requestData]);
    }

    /**
     * @inheritDoc
     */
    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $threeDAuthStatus = ('Y' === $raw3DAuthResponseData['Status']) ? 'approved' : 'declined';
        $paymentResponseData = [];

        if ('approved' === $threeDAuthStatus) {
            $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);
        }

        $threeDResponse = [
            'id'            => $raw3DAuthResponseData['AuthCode'],
            'eci'           => $raw3DAuthResponseData['Eci'],
            'cavv'          => $raw3DAuthResponseData['Cavv'],
            'auth_code'     => null,
            'order_id'      => $raw3DAuthResponseData['VerifyEnrollmentRequestId'],
            'status'        => $threeDAuthStatus,
            'status_detail' => null,
            'error_code'    => 'declined' === $threeDAuthStatus ? $raw3DAuthResponseData['Status'] : null,
            'error_message' => null,
            'all'           => $rawPaymentResponseData,
            'ed_all'        => $raw3DAuthResponseData,
        ];

        if (empty($paymentResponseData)) {
            return (object) array_merge($this->getDefaultPaymentResponse(), $threeDResponse);
        }

        return (object) array_merge($threeDResponse, $paymentResponseData);
    }

    /**
     * TODO
     * @inheritDoc
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    protected function mapRefundResponse($rawResponseData)
    {
        return $this->mapCancelResponse($rawResponseData);
    }

    /**
     * @inheritDoc
     */
    protected function mapCancelResponse($rawResponseData)
    {
        $status = 'declined';
        if ('0000' === $rawResponseData->ResultCode) {
            $status = 'approved';
        }

        return (object) [
            'order_id'         => $rawResponseData->TransactionId,
            'auth_code'        => ('declined' !== $status) ? $rawResponseData->AuthCode : null,
            'host_ref_num'     => isset($rawResponseData->Rrn) ? $rawResponseData->Rrn : null,
            'proc_return_code' => $rawResponseData->ResultCode,
            'trans_id'         => $rawResponseData->TransactionId,
            'error_code'       => ('declined' === $status) ? $rawResponseData->ResultDetail : null,
            'error_message'    => ('declined' === $status) ? $rawResponseData->ResultDetail : null,
            'status'           => $status,
            'status_detail'    => $rawResponseData->ResultDetail,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapPaymentResponse($responseData)
    {
        $status = 'declined';
        if ('0000' === $responseData->ResultCode) {
            $status = 'approved';
        }

        return [
            'id'               => $responseData->AuthCode,
            'trans_id'         => $responseData->TransactionId,
            'auth_code'        => $responseData->AuthCode,
            'host_ref_num'     => $responseData->Rrn,
            'order_id'         => $responseData->OrderId,
            'transaction'      => $this->type,
            'transaction_type' => $responseData->TransactionType,
            'proc_return_code' => $responseData->ResultCode,
            'code'             => $responseData->ResultCode,
            'status'           => $status,
            'status_detail'    => $responseData->ResultDetail,
            'error_code'       => ('declined' === $status) ? $responseData->ResultCode : null,
            'error_message'    => ('declined' === $status) ? $responseData->ResultDetail : null,
            'all'              => $responseData,
        ];
    }

    /**
     * TODO
     * @inheritDoc
     */
    protected function mapStatusResponse($rawResponseData)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    protected function mapHistoryResponse($rawResponseData)
    {
        return $rawResponseData;
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order)
    {
        // Installment
        $installment = 0;
        if (isset($order['installment']) && $order['installment'] > 1) {
            $installment = (int) $order['installment'];
        }

        $currency = isset($order['currency']) ? $order['currency'] : 'TRY';

        // Order
        return (object) array_merge($order, [
            'installment' => $installment,
            'currency'    => $this->mapCurrency($currency),
            'amount'      => self::amountFormat($order['amount']),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order)
    {
        return (object) [
            'id'       => $order['id'],
            'amount'   => self::amountFormat($order['amount']),
            'currency' => $this->mapCurrency($order['currency']),
            'ip'       => $order['ip'],
        ];
    }

    /**
     * TODO
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order)
    {
        return (object) $order;
    }

    /**
     * TODO
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $order)
    {
        return (object) [
            'id' => isset($order['id']) ? $order['id'] : null,
        ];
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
        $order['amount'] = self::amountFormat($order['amount']);

        return (object) $order;
    }
}

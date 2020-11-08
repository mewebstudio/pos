<?php


namespace Mews\Pos;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCardEstPos;
use Mews\Pos\Entity\Card\CreditCardPayFor;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Class PayForPos
 */
class PayForPos implements PosInterface
{
    use PosHelpersTrait {
        createXML as traitCreateXML;
    }

    const LANG_TR = 'tr';
    const LANG_EN = 'en';

    /**
     * Response Codes
     *
     * @var array
     */
    protected $codes = [
        '00' => 'approved',
        '96' => 'general_error',
        'V004' => 'invalid_credentials',
        'V001' => 'invalid_credentials',
        'V111' => 'general_error',
        'V013' => 'reject',
        'V014' => 'request_rejected',
        'V015' => 'request_rejected',
        'V025' => 'general_error',
        'V029' => 'general_error',
        'V034' => 'try_again',
        'V036' => 'general_error',
        'M025' => 'general_error',
        'M042' => 'general_error',
        'M002' => 'invalid_transaction',
        'M012' => 'invalid_transaction',
        'MR15' => 'try_again',
        'M041' => 'reject',
        'M049' => 'invalid_credentials',
    ];

    private $config;
    private $account;

    /**
     * Transaction Types
     *
     * @var array
     */
    private $types = [
        'pay' => 'Auth',
        'pre' => 'PreAuth',
        'post' => 'PostAuth',
        'cancel' => 'Void',
        'refund' => 'Refund',
        'history' => 'TxnHistory',
        'status' => 'OrderInquiry',
    ];

    /**
     * Transaction Type
     *
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $currencies;

    /**
     * @var object
     */
    private $order;

    /**
     * @var CreditCardPayFor
     */
    private $card;

    /**
     * Processed Response Data
     *
     * @var object
     */
    private $response;

    /**
     * Raw Response Data
     *
     * @var object
     */
    protected $data;

    public function __construct($config, $account, array $currencies)
    {
        $this->config = $config;
        $this->account = $account;

        if(!isset($this->account->lang)) $this->account->lang = self::LANG_TR;

        $this->currencies = $currencies;

        $this->url = isset($this->config['urls'][$this->account->env]) ?
            $this->config['urls'][$this->account->env] :
            $this->config['urls']['production'];

        $this->gateway = isset($this->config['urls']['gateway'][$this->account->env]) ?
            $this->config['urls']['gateway'][$this->account->env] :
            $this->config['urls']['gateway']['production'];

        $this->gateway3DHost = isset($this->config['urls']['gateway_3d_host'][$this->account->env]) ?
            $this->config['urls']['gateway_3d_host'][$this->account->env] :
            $this->config['urls']['gateway_3d_host']['production'];
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
     * @return mixed
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return CreditCardPayFor|null
     */
    public function getCard()
    {
        return $this->card;
    }

    /**
     * @param AbstractCreditCard $card
     *
     * @return $this
     *
     * @throws GuzzleException
     * @throws UnsupportedPaymentModelException
     */
    public function payment($card)
    {
        $this->card = $card;

        $model = 'regular';
        if (isset($this->account->model) && $this->account->model) {
            $model = $this->account->model;
        }

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
     * Regular Payment
     *
     * @return $this
     *
     * @throws GuzzleException
     */
    public function makeRegularPayment()
    {
        $contents = '';
        if (in_array($this->order->transaction, ['pay', 'pre'])) {
            $contents = $this->createRegularPaymentXML();
        } elseif ('post' === $this->order->transaction) {
            $contents = $this->createRegularPostXML();
        }

        $this->send($contents);

        $this->response = (object) $this->mapPaymentResponse($this->data);

        return $this;
    }

    /**
     * Make 3D Payment
     * TODO 3d authorization basarili durumda procreturncode v033 donuyor.
     * @return $this
     *
     * @throws GuzzleException
     */
    public function make3DPayment()
    {
        $request = Request::createFromGlobals();

        //if customer 3d verification passed finish payment
        if ($this->check3DHash($request->request->all()) && '1' === $request->get('3DStatus')) {
            $contents = $this->create3DPaymentXML($request->request->all());
            $this->send($contents);
        }

        $this->response = $this->map3DPaymentData($request->request->all(), $this->data);

        return $this;
    }

    /**
     * Just returns formatted data of 3dPay payment response
     *
     * @return $this
     */
    public function make3DPayPayment()
    {
        $request = Request::createFromGlobals();

        $this->response = $this->map3DPayResponseData($request->request->all());

        return $this;
    }

    /**
     * Just returns formatted data of host payment response
     *
     * @return $this
     */
    public function make3DHostPayment()
    {
        return $this->make3DPayPayment();
    }

    /**
     * Refund Order
     * refund amount should be exactly the same with order amount.
     * otherwise operation will be rejected
     * TODO:
     * Bu hatayı "Bu işlem geri alınamaz, lüften asıl işlemi iptal edin." alıyorsanız,
     * sebebi ödemeyi aynı gün içinde iade etmek istiyorsanız CANCEL işlemi kullanılmalıdır,
     * Refund işlemi en az 1 gün geçmiş işlemler için kullanabilirsiniz.
     *
     * @param array $meta
     *
     * @return $this
     *
     * @throws GuzzleException
     */
    public function refund(array $meta)
    {
        $xml = $this->createRefundXML();
        $this->send($xml);

        $this->response = $this->mapRefundResponse($this->data);

        return $this;
    }

    /**
     * Cancel Order
     *
     * @param array $meta
     *
     * @return $this
     *
     * @throws GuzzleException
     */
    public function cancel(array $meta)
    {
        $xml = $this->createCancelXML();
        $this->send($xml);

        $this->response = $this->mapCancelResponse($this->data);

        return $this;
    }

    /**
     * Order Status
     *
     * @param array $meta
     *
     * @return $this
     *
     * @throws GuzzleException
     */
    public function status(array $meta)
    {
        $xml = $this->createOrderStatusXML();

        $this->send($xml);

        $this->response = $this->mapStatusResponse($this->data);

        return $this;
    }

    /**
     * Fetches All Transaction/Action/Order history, both failed and successful, for the given date ReqDate
     * or single order if orderId is given
     * Note: history request to gateway returns JSON response
     *
     * @param array $meta
     *
     * @return $this
     *
     * @throws GuzzleException
     */
    public function history(array $meta)
    {
        $xml = $this->createHistoryXML($meta);

        $this->send($xml);

        //returns list array or items, if orderId specified in request then return array with single item
        $this->response = (array) $this->data;

        return $this;
    }


    /**
     * returns data needed for 3d, 3d_pay and 3d_host models
     *
     * @return array
     */
    public function get3dFormData()
    {
        if (!$this->order) {
            return [];
        }

        $this->order->hash = $this->create3DHash();

        if ('3d_pay' === $this->account->model) {
            $formData = $this->getCommon3DFormData();
            $formData['inputs']['SecureType'] = '3DPay';
            $formData['gateway'] = $this->gateway;
        } elseif ('3d' === $this->account->model) {
            $formData = $this->getCommon3DFormData();
            $formData['inputs']['SecureType'] = '3DModel';
            $formData['gateway'] = $this->gateway;
        } else {
            $formData = $this->getCommon3DFormData();
            $formData['inputs']['SecureType'] = '3DHost';
            $formData['gateway'] = $this->gateway3DHost;
        }

        return $formData;
    }

    /**
     * @param object           $order
     * @param CreditCardPayFor $card
     *
     * @return void
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function prepare($order, $card = null)
    {
        $this->type = $this->types['pay'];
        if (isset($order->transaction)) {
            if (array_key_exists($order->transaction, $this->types)) {
                $this->type = $this->types[$order->transaction];
            } else {
                throw new UnsupportedTransactionTypeException('Unsupported transaction type!');
            }
        }

        $this->order = $order;
        $this->card = $card;
    }

    /**
     * @param $postData
     *
     * @return $this|PayForPos
     *
     * @throws GuzzleException
     */
    public function send($postData)
    {
        $client = new Client();

        $response = $client->request('POST', $this->url, [
            'body' => $postData,
        ]);

        $contents = $response->getBody()->getContents();

        /**
         * Finansbank XML Response some times are in following format:
         * <MbrId>5</MbrId>\r\n
         * <MD>\r\n
         * </MD>\r\n
         * <Hash>\r\n
         * </Hash>\r\n
         * redundant whitespaces causes non empty value for response properties
         */
        $contents = preg_replace('/\\r\\n  /', '', $contents);

        try {
            $this->data = $this->XMLStringToObject($contents);
        } catch (NotEncodableValueException $e) {
            //Finansbank's history request response is in JSON format
            $this->data = (object) json_decode($contents);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function createXML(array $data, $encoding = 'UTF-8'): string
    {
        return $this->traitCreateXML(['PayforRequest' => $data], $encoding);
    }


    /**
     * @return string
     */
    public function create3DHash()
    {
        $hashStr = $this->account->customData->mbrId . $this->order->id
            . $this->order->amount . $this->order->success_url
            . $this->order->fail_url . $this->type
            . $this->order->installment . $this->order->rand
            . $this->account->store_key;

        return base64_encode(pack('H*', sha1($hashStr)));
    }

    /**
     * validates response hash
     *
     * @param array $data
     *
     * @return bool
     */
    public function check3DHash($data)
    {

        $hashStr = $this->account->client_id . $this->account->store_key
            . $data['OrderId'] . $data['AuthCode']
            . $data['ProcReturnCode'] . $data['3DStatus']
            . $data['ResponseRnd'] . $this->account->username;

        $hash = base64_encode(pack('H*', sha1($hashStr)));

        return $hash === $data['ResponseHash'];
    }

    /**
     * @param array  $raw3DAuthResponseData
     * @param object $rawPaymentResponseData
     *
     * @return object
     */
    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $threeDAuthStatus = ('1' === $raw3DAuthResponseData['3DStatus']) ? 'approved' : 'declined';
        $paymentResponseData = [];

        if ('approved' === $threeDAuthStatus) {
            $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);
        }

        $threeDResponse = [
            'id' => $raw3DAuthResponseData['AuthCode'],
            'trans_id' => $raw3DAuthResponseData['OrderId'],
            'auth_code' => $raw3DAuthResponseData['AuthCode'],
            'host_ref_num' => $raw3DAuthResponseData['HostRefNum'],
            'order_id' => $raw3DAuthResponseData['OrderId'],
            'proc_return_code' => $raw3DAuthResponseData['ProcReturnCode'],
            'code' => $raw3DAuthResponseData['ProcReturnCode'],
            'status' => 'declined',
            'status_detail' => isset($this->codes[$raw3DAuthResponseData['ProcReturnCode']]) ? $this->codes[$raw3DAuthResponseData['ProcReturnCode']] : null,
            'error_code' => $raw3DAuthResponseData['ProcReturnCode'],
            'error_message' => $raw3DAuthResponseData['ErrMsg'],
        ];

        if (empty($paymentResponseData)) {
            return (object) array_merge($this->getDefaultPaymentResponse(), $threeDResponse, $this->map3DCommonResponseData($raw3DAuthResponseData));
        }

        return (object) array_merge($threeDResponse, $this->map3DCommonResponseData($raw3DAuthResponseData), $paymentResponseData);
    }

    /**
     * @param array $raw3DAuthResponseData
     *
     * @return object
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        $status = '00' === $raw3DAuthResponseData['ProcReturnCode'] ? 'approved' : 'declined';

        $threeDResponse = [
            'id' => $raw3DAuthResponseData['AuthCode'],
            'trans_id' => $raw3DAuthResponseData['OrderId'],
            'auth_code' => $raw3DAuthResponseData['AuthCode'],
            'host_ref_num' => $raw3DAuthResponseData['HostRefNum'],
            'order_id' => $raw3DAuthResponseData['OrderId'],
            'proc_return_code' => $raw3DAuthResponseData['ProcReturnCode'],
            'code' => $raw3DAuthResponseData['ProcReturnCode'],
            'status' => $status,
            'status_detail' => isset($this->codes[$raw3DAuthResponseData['ProcReturnCode']]) ? $this->codes[$raw3DAuthResponseData['ProcReturnCode']] : null,
            'error_code' => ('approved' !== $status) ? $raw3DAuthResponseData['ProcReturnCode'] : null,
            'error_message' => ('approved' !== $status) ? $raw3DAuthResponseData['ErrMsg'] : null,
            'transaction_type' => array_search($raw3DAuthResponseData['TxnType'], $this->types, true),
            'transaction' => $this->order->transaction,
        ];

        return (object) array_merge($threeDResponse, $this->map3DCommonResponseData($raw3DAuthResponseData));
    }

    /**
     * returns mapped data of the common response data among all 3d models.
     * @param $raw3DAuthResponseData
     *
     * @return array
     */
    protected function map3DCommonResponseData($raw3DAuthResponseData)
    {
        $threeDAuthStatus = ('1' === $raw3DAuthResponseData['3DStatus']) ? 'approved' : 'declined';

        return [
            'transaction_security' => $raw3DAuthResponseData['SecureType'],
            'hash' => $raw3DAuthResponseData['ResponseHash'],
            'rand' => $raw3DAuthResponseData['ResponseRnd'],
            'masked_number' => $raw3DAuthResponseData['CardMask'],
            'amount' => $raw3DAuthResponseData['PurchAmount'],
            'currency' => array_search($raw3DAuthResponseData['Currency'], $this->currencies),
            'tx_status' => $raw3DAuthResponseData['TxnResult'],
            'xid' => $raw3DAuthResponseData['PayerTxnId'],
            'md_code' => $raw3DAuthResponseData['ProcReturnCode'],
            'md_status' => $raw3DAuthResponseData['3DStatus'],
            'md_error_code' => ('declined' === $threeDAuthStatus) ? $raw3DAuthResponseData['ProcReturnCode'] : null,
            'md_error_message' => ('declined' === $threeDAuthStatus) ? $raw3DAuthResponseData['ErrMsg'] : null,
            'md_status_detail' => isset($this->codes[$raw3DAuthResponseData['ProcReturnCode']]) ? $this->codes[$raw3DAuthResponseData['ProcReturnCode']] : null,
            'eci' => $raw3DAuthResponseData['Eci'],
            '3d_all' => $raw3DAuthResponseData,
        ];
    }

    /**
     * @param $rawResponseData
     *
     * @return object
     */
    protected function mapRefundResponse($rawResponseData)
    {

        $status = 'declined';
        if ('00' === $rawResponseData->ProcReturnCode) {
            $status = 'approved';
        }

        return (object) [
            'order_id' => isset($rawResponseData->TransId) ? $rawResponseData->TransId : null,
            'auth_code' => ('declined' !== $status) ? $rawResponseData->AuthCode : null,
            'host_ref_num' => isset($rawResponseData->HostRefNum) ? $rawResponseData->HostRefNum : null,
            'proc_return_code' => isset($rawResponseData->ProcReturnCode) ? $rawResponseData->ProcReturnCode : null,
            'trans_id' => isset($rawResponseData->TransId) ? $rawResponseData->TransId : null,
            'error_code' => ('declined' === $status) ? $rawResponseData->ProcReturnCode : null,
            'error_message' => ('declined' === $status) ? $rawResponseData->ErrMsg : null,
            'status' => $status,
            'status_detail' => isset($this->codes[$rawResponseData->ProcReturnCode]) ? $this->codes[$rawResponseData->ProcReturnCode] : null,
            'all' => $rawResponseData,
        ];
    }

    protected function mapCancelResponse($rawResponseData){

        $status = 'declined';
        if ('00' === $rawResponseData->ProcReturnCode) {
            $status = 'approved';
        }

        return (object) [
            'order_id' => isset($rawResponseData->TransId) ? $rawResponseData->TransId : null,
            'auth_code' => ('declined' !== $status) ? $rawResponseData->AuthCode : null,
            'host_ref_num' => isset($rawResponseData->HostRefNum) ? $rawResponseData->HostRefNum : null,
            'proc_return_code' => isset($rawResponseData->ProcReturnCode) ? $rawResponseData->ProcReturnCode : null,
            'trans_id' => isset($rawResponseData->TransId) ? $rawResponseData->TransId : null,
            'error_code' => ('declined' === $status) ? $rawResponseData->ProcReturnCode : null,
            'error_message' => ('declined' === $status) ? $rawResponseData->ErrMsg : null,
            'status' => $status,
            'status_detail' => isset($this->codes[$rawResponseData->ProcReturnCode]) ? $this->codes[$rawResponseData->ProcReturnCode] : null,
            'all' => $rawResponseData,
        ];
    }


    /**
     * Processes payment response data
     *
     * @param object $responseData
     *
     * @return array
     */
    protected function mapPaymentResponse($responseData)
    {
        $status = 'declined';
        if ('00' === $responseData->ProcReturnCode) {
            $status = 'approved';
        }

        return [
            'id' => $responseData->AuthCode,
            'order_id' => $responseData->TransId,
            'trans_id' => $responseData->TransId,
            'transaction_type' => $this->type,
            'transaction' => $this->type,
            'auth_code' => $responseData->AuthCode,
            'host_ref_num' => $responseData->HostRefNum,
            'proc_return_code' => $responseData->ProcReturnCode,
            'code' => $responseData->ProcReturnCode,
            'status' => $status,
            'status_detail' => isset($this->codes[$responseData->ProcReturnCode]) ? $this->codes[$responseData->ProcReturnCode] : null,
            'error_code' => ('declined' === $status) ? $responseData->ProcReturnCode : null,
            'error_message' => ('declined' === $status) ? $responseData->ErrMsg : null,
            'all' => $responseData,
        ];
    }

    /**
     * @param object $rawResponseData
     *
     * @return object
     */
    protected function mapStatusResponse($rawResponseData)
    {
        //status of the request
        $status = 'declined';
        if ('00' === $rawResponseData->ProcReturnCode) {
            $status = 'approved';
        }

        //status of the requested order
        $orderStatus = null;
        if ('approved' === $status && empty($rawResponseData->AuthCode)) {
            $orderStatus = 'declined';
        } elseif ('approved' === $status && !empty($rawResponseData->AuthCode)) {
            $orderStatus = 'approved';
        }

        return (object) [
            'auth_code' => isset($rawResponseData->AuthCode) ? $rawResponseData->AuthCode : null,
            'order_id' => isset($rawResponseData->OrderId) ? $rawResponseData->OrderId : null,
            'org_order_id' => isset($rawResponseData->OrgOrderId) ? $rawResponseData->OrgOrderId : null,
            'proc_return_code' => isset($rawResponseData->ProcReturnCode) ? $rawResponseData->ProcReturnCode : null,
            'error_message' => ('declined' === $status) ? $rawResponseData->ErrMsg : null,
            'host_ref_num' => isset($rawResponseData->HostRefNum) ? $rawResponseData->HostRefNum : null,
            'order_status' => $orderStatus,
            'process_type' => isset($rawResponseData->TxnType) ? array_search($rawResponseData->TxnType, $this->types, true) : null,
            'masked_number' => isset($rawResponseData->CardMask) ? $rawResponseData->CardMask : null,
            'amount' => isset($rawResponseData->PurchAmount) ? $rawResponseData->PurchAmount : null,
            'currency' => isset($rawResponseData->Currency) ? array_search($rawResponseData->Currency, $this->currencies) : null,
            'status' => $status,
            'status_detail' => isset($this->codes[$rawResponseData->ProcReturnCode]) ? $this->codes[$rawResponseData->ProcReturnCode] : null,
            'all' => $rawResponseData,
        ];
    }

    /**
     * Returns payment default response data
     *
     * @return array
     */
    protected function getDefaultPaymentResponse()
    {
        return [
            'id' => null,
            'order_id' => null,
            'trans_id' => null,
            'transaction_type' => $this->type,
            'transaction' => $this->type,
            'auth_code' => null,
            'host_ref_num' => null,
            'proc_return_code' => null,
            'code' => null,
            'status' => 'declined',
            'status_detail' => null,
            'error_code' => null,
            'error_message' => null,
            'all' => null,
        ];
    }


    /**
     * Create Regular Payment XML
     *
     * @return string
     */
    protected function createRegularPaymentXML()
    {
        $requestData = [
            'MbrId' => $this->account->customData->mbrId,
            'MerchantId' => $this->account->client_id,
            'UserCode' => $this->account->username,
            'UserPass' => $this->account->password,
            'MOTO' => $this->account->customData->moto,
            'OrderId' => $this->order->id,
            'SecureType' => 'NonSecure',
            'TxnType' => $this->type,
            'PurchAmount' => $this->order->amount,
            'Currency' => $this->order->currency,
            'InstallmentCount' => $this->order->installment,
            'Lang' => $this->getLang(),
            'CardHolderName' => $this->card->getHolderName(),
            'Pan' => $this->card->getNumber(),
            'Expiry' => $this->card->getExpirationDate(),
            'Cvv2' => $this->card->getCvv(),
        ];

        return $this->createXML($requestData);
    }

    /**
     * Create Regular Payment Post XML
     *
     * @return string
     */
    protected function createRegularPostXML()
    {
        $requestData = [
            'MbrId' => $this->account->customData->mbrId,
            'MerchantId' => $this->account->client_id,
            'UserCode' => $this->account->username,
            'UserPass' => $this->account->password,
            'MOTO' => $this->account->customData->moto,
            'OrgOrderId' => $this->order->id,
            'SecureType' => 'NonSecure',
            'TxnType' => $this->type,
            'PurchAmount' => $this->order->amount,
            'Currency' => $this->order->currency,
            'Lang' => $this->getLang(),
            'CardHolderName' => $this->card->getHolderName(),
            'Pan' => $this->card->getNumber(),
            'Expiry' => $this->card->getExpirationDate(),
            'Cvv2' => $this->card->getCvv(),
        ];

        return $this->createXML($requestData);
    }

    /**
     * Creates 3D Payment XML
     * @param $responseData
     *
     * @return string
     */
    protected function create3DPaymentXML($responseData)
    {
        $requestData = [
            'RequestGuid' => $responseData['RequestGuid'],
            'UserCode' => $this->account->username,
            'UserPass' => $this->account->password,
            'OrderId' => $this->order->id,
            'SecureType' => '3DModelPayment',
        ];

        return $this->createXML($requestData);
    }

    /**
     * Creates XML string for order status inquiry
     *
     * @return string
     */
    protected function createOrderStatusXML()
    {
        $requestData = [
            'MbrId' => $this->account->customData->mbrId,
            'MerchantId' => $this->account->client_id,
            'UserCode' => $this->account->username,
            'UserPass' => $this->account->password,
            'OrgOrderId' => $this->order->id,
            'SecureType' => 'Inquiry',
            'Lang' => $this->getLang(),
            'TxnType' => $this->types['status'],
        ];

        return $this->createXML($requestData);
    }

    /**
     * Creates XML string for order refund operation
     *
     * @return string
     */
    protected function createRefundXML()
    {
        $requestData = [
            'MbrId' => $this->account->customData->mbrId,
            'MerchantId' => $this->account->client_id,
            'UserCode' => $this->account->username,
            'UserPass' => $this->account->password,
            'SecureType' => 'NonSecure',
            'Lang' => $this->getLang(),
            'OrgOrderId' => $this->order->id,
            'TxnType' => $this->types['refund'],
            'PurchAmount' => $this->order->amount,
            'Currency' => $this->order->currency,
        ];

        return $this->createXML($requestData);
    }

    /**
     * Creates XML string for order cancel operation
     *
     * @return string
     */
    protected function createCancelXML()
    {
        $requestData = [
            'MbrId' => $this->account->customData->mbrId,
            'MerchantId' => $this->account->client_id,
            'UserCode' => $this->account->username,
            'UserPass' => $this->account->password,
            'OrgOrderId' => $this->order->id,
            'SecureType' => 'NonSecure',
            'TxnType' => $this->types['cancel'],
            'Currency' => $this->order->currency,
            'Lang' => $this->getLang(),
        ];

        return $this->createXML($requestData);
    }


    /**
     * Creates XML string for history inquiry
     *
     * @param array $customQueryData
     *
     * @return string
     */
    protected function createHistoryXML($customQueryData)
    {
        $requestData = [
            'MbrId' => $this->account->customData->mbrId,
            'MerchantId' => $this->account->client_id,
            'UserCode' => $this->account->username,
            'UserPass' => $this->account->password,
            'SecureType' => 'Report',
            'TxnType' => $this->types['history'],
            'Lang' => $this->getLang(),
        ];

        if (isset($customQueryData['orderId'])) {
            $requestData['OrderId'] = $customQueryData['orderId'];
        } elseif (isset($customQueryData['ReqDate'])) {
            //ReqData YYYYMMDD format
            $requestData['ReqDate'] = $customQueryData['reqDate'];
        }


        return $this->createXML($requestData);
    }

    /**
     * returns common form data used by all 3D payment gates
     * @return array
     */
    protected function getCommon3DFormData($withCrediCard = false)
    {
        $inputs = [
            'MbrId' => $this->account->customData->mbrId,
            'MerchantID' => $this->account->client_id,
            'UserCode' => $this->account->username,
            'OrderId' => $this->order->id,
            'Lang' => $this->getLang(),
            'SecureType' => null,
            'TxnType' => $this->type,
            'PurchAmount' => $this->order->amount,
            'InstallmentCount' => $this->order->installment,
            'Currency' => $this->order->currency,
            'OkUrl' => $this->order->success_url,
            'FailUrl' => $this->order->fail_url,
            'Rnd' => $this->order->rand,
            'Hash' => $this->order->hash,
        ];

        if ($withCrediCard) {
            $inputs['CardHolderName'] = $this->card->getHolderName();
            $inputs['Pan'] = $this->card->getNumber();
            $inputs['Expiry'] = $this->card->getExpirationDate();
            $inputs['Cvv2'] = $this->card->getCvv();
        }

        return [
            'gateway' => null,
            'inputs' => $inputs,
        ];
    }

    /**
     * bank returns error messages for specified language value
     * usually accepted values are tr,en
     * @return string
     */
    private function getLang()
    {
        if ($this->order && isset($this->order->lang)) {
            return $this->order->lang;
        }

        return $this->account->lang;
    }

}
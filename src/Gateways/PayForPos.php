<?php


namespace Mews\Pos\Gateways;

use GuzzleHttp\Client;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Card\CreditCardPayFor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Class PayForPos
 */
class PayForPos extends AbstractGateway
{
    const LANG_TR = 'tr';
    const LANG_EN = 'en';

    /**
     * Kurum kodudur. (Banka tarafından verilir)
     */
    const MBR_ID = '5';

    /**
     * MOTO (Mail Order Telephone Order) 0 for false, 1 for true
     */
    const MOTO = '0';

    /**
     * @var PayForAccount
     */
    protected $account;

    /**
     * @var CreditCardPayFor
     */
    protected $card;

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

    /**
     * Transaction Types
     *
     * @var array
     */
    protected $types = [
        self::TX_PAY => 'Auth',
        self::TX_PRE_PAY => 'PreAuth',
        self::TX_POST_PAY => 'PostAuth',
        self::TX_CANCEL => 'Void',
        self::TX_REFUND => 'Refund',
        self::TX_HISTORY => 'TxnHistory',
        self::TX_STATUS => 'OrderInquiry',
    ];

    /**
     * currency mapping
     *
     * @var array
     */
    protected $currencies = [
        'TRY'       => 949,
        'USD'       => 840,
        'EUR'       => 978,
        'GBP'       => 826,
        'JPY'       => 392,
        'RUB'       => 643,
    ];

    /**
     * @inheritDoc
     *
     * @param PayForAccount $account
     */
    public function __construct($config, $account, array $currencies)
    {
        parent::__construct($config, $account, $currencies);
    }

    /**
     * @return PayForAccount
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @return CreditCardPayFor
     */
    public function getCard()
    {
        return $this->card;
    }

    /**
     * @param CreditCardPayFor|null $card
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
        $request = Request::createFromGlobals();

        //if customer 3d verification passed finish payment
        if ($this->check3DHash($request->request->all()) && '1' === $request->get('3DStatus')) {
            //valid ProcReturnCode is V033 in case of success 3D Authentication
            $contents = $this->create3DPaymentXML($request->request->all());
            $this->send($contents);
        }

        $this->response = $this->map3DPaymentData($request->request->all(), $this->data);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment()
    {
        $request = Request::createFromGlobals();

        $this->response = $this->map3DPayResponseData($request->request->all());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment()
    {
        return $this->make3DPayPayment();
    }

    /**
     * Refund Order
     * refund amount should be exactly the same with order amount.
     * otherwise operation will be rejected
     *
     * Warning: You can not use refund for purchases made at the same date.
     * Instead, you need to use cancel.
     *
     * @inheritDoc
     */
    public function refund()
    {
        return parent::refund();
    }

    /**
     * Fetches All Transaction/Action/Order history, both failed and successful, for the given date ReqDate
     * or transactions related to the queried order if orderId is given
     * Note: history request to gateway returns JSON response
     * If both reqDate and orderId provided then finansbank will take into account only orderId
     *
     * returns list array or items for the given date,
     * if orderId specified in request then return array of transactions (refund|pre|post|cancel)
     * both successful and failed, for the related orderId
     * @inheritDoc
     */
    public function history(array $meta)
    {
        return parent::history($meta);
    }


    /**
     * returns form data needed for 3d, 3d_pay and 3d_host models
     *
     * @return array
     */
    public function get3DFormData()
    {
        if (!$this->order) {
            return [];
        }

        $this->order->hash = $this->create3DHash();

        $formData = $this->getCommon3DFormData();
        if ('3d_pay' === $this->account->getModel()) {
            $formData['inputs']['SecureType'] = '3DPay';
            $formData['gateway'] = $this->get3DGatewayURL();
        } elseif ('3d' === $this->account->getModel()) {
            $formData['inputs']['SecureType'] = '3DModel';
            $formData['gateway'] = $this->get3DGatewayURL();
        } else {
            $formData['inputs']['SecureType'] = '3DHost';
            $formData['gateway'] = $this->get3DHostGatewayURL();
        }

        return $formData;
    }



    /**
     * @inheritDoc
     */
    public function send($postData)
    {
        $client = new Client();

        $response = $client->request('POST', $this->getApiURL(), [
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
        return parent::createXML(['PayforRequest' => $data], $encoding);
    }


    /**
     * @return string
     */
    public function create3DHash()
    {
        $hashStr = self::MBR_ID . $this->order->id
            . $this->order->amount . $this->order->success_url
            . $this->order->fail_url . $this->type
            . $this->order->installment . $this->order->rand
            . $this->account->getStoreKey();

        return base64_encode(sha1($hashStr, true));
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

        $hashStr = $this->account->getClientId() . $this->account->getStoreKey()
            . $data['OrderId'] . $data['AuthCode']
            . $data['ProcReturnCode'] . $data['3DStatus']
            . $data['ResponseRnd'] . $this->account->getUsername();

        $hash = base64_encode(sha1($hashStr, true));

        return $hash === $data['ResponseHash'];
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML()
    {
        $requestData = [
            'MbrId' => self::MBR_ID,
            'MerchantId' => $this->account->getClientId(),
            'UserCode' => $this->account->getUsername(),
            'UserPass' => $this->account->getPassword(),
            'MOTO' => self::MOTO,
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
     * @inheritDoc
     */
    public function createRegularPostXML()
    {
        $requestData = [
            'MbrId' => self::MBR_ID,
            'MerchantId' => $this->account->getClientId(),
            'UserCode' => $this->account->getUsername(),
            'UserPass' => $this->account->getPassword(),
            'OrgOrderId' => $this->order->id,
            'SecureType' => 'NonSecure',
            'TxnType' => $this->type,
            'PurchAmount' => $this->order->amount,
            'Currency' => $this->order->currency,
            'Lang' => $this->getLang(),
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        $requestData = [
            'RequestGuid' => $responseData['RequestGuid'],
            'UserCode' => $this->account->getUsername(),
            'UserPass' => $this->account->getPassword(),
            'OrderId' => $this->order->id,
            'SecureType' => '3DModelPayment',
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createStatusXML()
    {
        $requestData = [
            'MbrId' => self::MBR_ID,
            'MerchantId' => $this->account->getClientId(),
            'UserCode' => $this->account->getUsername(),
            'UserPass' => $this->account->getPassword(),
            'OrgOrderId' => $this->order->id,
            'SecureType' => 'Inquiry',
            'Lang' => $this->getLang(),
            'TxnType' => $this->types[self::TX_STATUS],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {
        $requestData = [
            'MbrId' => self::MBR_ID,
            'MerchantId' => $this->account->getClientId(),
            'UserCode' => $this->account->getUsername(),
            'UserPass' => $this->account->getPassword(),
            'SecureType' => 'Report',
            'TxnType' => $this->types[self::TX_HISTORY],
            'Lang' => $this->getLang(),
        ];

        if (isset($customQueryData['orderId'])) {
            $requestData['OrderId'] = $customQueryData['orderId'];
        } elseif (isset($customQueryData['reqDate'])) {
            //ReqData YYYYMMDD format
            $requestData['ReqDate'] = $customQueryData['reqDate'];
        }


        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {
        $requestData = [
            'MbrId' => self::MBR_ID,
            'MerchantId' => $this->account->getClientId(),
            'UserCode' => $this->account->getUsername(),
            'UserPass' => $this->account->getPassword(),
            'SecureType' => 'NonSecure',
            'Lang' => $this->getLang(),
            'OrgOrderId' => $this->order->id,
            'TxnType' => $this->types[self::TX_REFUND],
            'PurchAmount' => $this->order->amount,
            'Currency' => $this->order->currency,
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {
        $requestData = [
            'MbrId' => self::MBR_ID,
            'MerchantId' => $this->account->getClientId(),
            'UserCode' => $this->account->getUsername(),
            'UserPass' => $this->account->getPassword(),
            'OrgOrderId' => $this->order->id,
            'SecureType' => 'NonSecure',
            'TxnType' => $this->types[self::TX_CANCEL],
            'Currency' => $this->order->currency,
            'Lang' => $this->getLang(),
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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
            'transaction' => $this->type,
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
     * @inheritDoc
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
     * @inheritDoc
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
     * returns common form data used by all 3D payment gates
     *
     * @return array
     */
    protected function getCommon3DFormData()
    {
        $inputs = [
            'MbrId' => self::MBR_ID,
            'MerchantID' => $this->account->getClientId(),
            'UserCode' => $this->account->getUsername(),
            'OrderId' => $this->order->id,
            'Lang' => $this->getLang(),
            'SecureType' => null, //to be filled by the caller
            'TxnType' => $this->type,
            'PurchAmount' => $this->order->amount,
            'InstallmentCount' => $this->order->installment,
            'Currency' => $this->order->currency,
            'OkUrl' => $this->order->success_url,
            'FailUrl' => $this->order->fail_url,
            'Rnd' => $this->order->rand,
            'Hash' => $this->order->hash,
        ];

        if ($this->card) {
            $inputs['CardHolderName'] = $this->card->getHolderName();
            $inputs['Pan'] = $this->card->getNumber();
            $inputs['Expiry'] = $this->card->getExpirationDate();
            $inputs['Cvv2'] = $this->card->getCvv();
        }

        return [
            'gateway' => null, //to be filled by the caller
            'inputs' => $inputs,
        ];
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
            'installment'   => $installment,
            'currency'      => $this->mapCurrency($currency),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order)
    {
        return (object) [
            'id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $this->mapCurrency($order['currency']),
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
        return (object) [
            //reqDate or order id
            'reqDate' => isset($order['reqDate']) ? $order['reqDate'] : null,
            'id' => isset($order['id']) ? $order['id'] : null,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order)
    {
        $order['currency'] = $this->mapCurrency($order['currency']);

        return (object) $order;
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order)
    {
        $order['currency'] = $this->mapCurrency($order['currency']);

        return (object) $order;
    }
}
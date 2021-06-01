<?php

namespace Mews\Pos\Gateways;

use GuzzleHttp\Client;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\CreditCardEstPos;
use Mews\Pos\Exceptions\NotImplementedException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class EstPos
 */
class EstPos extends AbstractGateway
{
    const LANG_TR = 'tr';
    const LANG_EN = 'en';

    /**
     * @const string
     */
    public const NAME = 'EstPos';

    /**
     * Response Codes
     *
     * @var array
     */
    protected $codes = [
        '00' => 'approved',
        '01' => 'bank_call',
        '02' => 'bank_call',
        '05' => 'reject',
        '09' => 'try_again',
        '12' => 'invalid_transaction',
        '28' => 'reject',
        '51' => 'insufficient_balance',
        '54' => 'expired_card',
        '57' => 'does_not_allow_card_holder',
        '62' => 'restricted_card',
        '77' => 'request_rejected',
        '99' => 'general_error',
    ];

    /**
     * Transaction Types
     *
     * @var array
     */
    protected $types = [
        self::TX_PAY      => 'Auth',
        self::TX_PRE_PAY  => 'PreAuth',
        self::TX_POST_PAY => 'PostAuth',
        self::TX_CANCEL   => 'Void',
        self::TX_REFUND   => 'Credit',
        self::TX_STATUS   => 'ORDERSTATUS',
        self::TX_HISTORY  => 'ORDERHISTORY',
    ];

    protected $recurringOrderFrequencyMapping = [
        'DAY'   => 'D',
        'WEEK'  => 'W',
        'MONTH' => 'M',
        'YEAR'  => 'Y',
    ];

    /**
     * Currency mapping
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
     * @var EstPosAccount
     */
    protected $account;

    /**
     * @var CreditCardEstPos|null
     */
    protected $card;

    /**
     * EstPos constructor.
     *
     * @param array         $config
     * @param EstPosAccount $account
     * @param array         $currencies
     */
    public function __construct($config, $account, array $currencies = [])
    {
        parent::__construct($config, $account, $currencies);
    }

    /**
     * @inheritDoc
     */
    public function createXML(array $data, $encoding = 'ISO-8859-9'): string
    {
        return parent::createXML(['CC5Request' => $data], $encoding);
    }

    /**
     * Create 3D Hash
     *
     * @return string
     */
    public function create3DHash()
    {
        $hashStr = '';

        if ($this->account->getModel() === '3d') {
            $hashStr = $this->account->getClientId().$this->order->id.$this->order->amount.$this->order->success_url.$this->order->fail_url.$this->order->rand.$this->account->getStoreKey();
        } elseif ($this->account->getModel() === '3d_pay') {
            $hashStr = $this->account->getClientId().$this->order->id.$this->order->amount.$this->order->success_url.$this->order->fail_url.$this->type.$this->order->installment.$this->order->rand.$this->account->getStoreKey();
        }

        return base64_encode(sha1($hashStr, true));
    }

    /**
     * Check 3D Hash
     *
     * @param array $data
     *
     * @return bool
     */
    public function check3DHash($data)
    {
        $hashParams = $data['HASHPARAMS'];
        $hashParamsVal = $data['HASHPARAMSVAL'];
        $hashParam = $data['HASH'];
        $paramsVal = '';

        $hashParamsArr = explode(':', $hashParams);
        foreach ($hashParamsArr as $value) {
            if (!empty($value) && isset($data[$value])) {
                $paramsVal = $paramsVal.$data[$value];
            }
        }

        $hashVal = $paramsVal.$this->account->getStoreKey();
        $hash = base64_encode(sha1($hashVal, true));

        $return = false;
        if ($hashParams && !($paramsVal !== $hashParamsVal || $hashParam !== $hash)) {
            $return = true;
        }

        return $return;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment()
    {
        $request = Request::createFromGlobals();


        if ($this->check3DHash($request->request->all())) {
            if ($request->request->get('ProcReturnCode') !== '00') {
                /**
                 * TODO hata durumu ele alinmasi gerekiyor
                 * ornegin soyle bir hata donebilir
                 * ["ProcReturnCode" => "99", "mdStatus" => "7", "mdErrorMsg" => "Isyeri kullanim tipi desteklenmiyor.",
                 * "ErrMsg" => "Isyeri kullanim tipi desteklenmiyor.", "Response" => "Error", "ErrCode" => "3D-1007", ...]
                 */
            }
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
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData()
    {
        if (!$this->order) {
            return [];
        }

        $this->order->hash = $this->create3DHash();

        $inputs = [
            'clientid'  => $this->account->getClientId(),
            'storetype' => $this->account->getModel(),
            'hash'      => $this->order->hash,
            'firmaadi'  => $this->order->name,
            'Email'     => $this->order->email,
            'amount'    => $this->order->amount,
            'oid'       => $this->order->id,
            'okUrl'     => $this->order->success_url,
            'failUrl'   => $this->order->fail_url,
            'rnd'       => $this->order->rand,
            'lang'      => $this->getLang(),
            'currency'  => $this->order->currency,
        ];

        if ($this->account->getModel() === '3d_pay') {
            $inputs = array_merge($inputs, [
                'islemtipi' => $this->type,
                'taksit'    => $this->order->installment,
            ]);
        }

        if ($this->card) {
            $inputs['cardType'] = $this->card->getCardCode();
            $inputs['pan'] = $this->card->getNumber();
            $inputs['Ecom_Payment_Card_ExpDate_Month'] = $this->card->getExpireMonth();
            $inputs['Ecom_Payment_Card_ExpDate_Year'] = $this->card->getExpireYear();
            $inputs['cv2'] = $this->card->getCvv();
        }

        return [
            'gateway' => $this->get3DGatewayURL(),
            'inputs'  => $inputs,
        ];
    }

    /**
     * @inheritDoc
     */
    public function send($contents)
    {
        $client = new Client();

        $response = $client->request('POST', $this->getApiURL(), [
            'body' => $contents,
        ]);

        $this->data = $this->XMLStringToObject($response->getBody()->getContents());

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
     * @return mixed
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @return CreditCardEstPos|null
     */
    public function getCard()
    {
        return $this->card;
    }

    /**
     * @param CreditCardEstPos|null $card
     */
    public function setCard($card)
    {
        $this->card = $card;
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML()
    {
        $requestData = [
            'Name'      => $this->account->getUsername(),
            'Password'  => $this->account->getPassword(),
            'ClientId'  => $this->account->getClientId(),
            'Type'      => $this->type,
            'IPAddress' => $this->order->ip,
            'Email'     => $this->order->email,
            'OrderId'   => $this->order->id,
            'UserId'    => isset($this->order->user_id) ? $this->order->user_id : null,
            'Total'     => $this->order->amount,
            'Currency'  => $this->order->currency,
            'Taksit'    => $this->order->installment,
            'CardType'  => $this->card->getType(),
            'Number'    => $this->card->getNumber(),
            'Expires'   => $this->card->getExpirationDate(),
            'Cvv2Val'   => $this->card->getCvv(),
            'Mode'      => 'P', //TODO what is this constant for?
            'GroupId'   => '',
            'TransId'   => '',
            'BillTo'    => [
                'Name' => $this->order->name ? $this->order->name : null,
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML()
    {
        $requestData = [
            'Name'     => $this->account->getUsername(),
            'Password' => $this->account->getPassword(),
            'ClientId' => $this->account->getClientId(),
            'Type'     => $this->types[self::TX_POST_PAY],
            'OrderId'  => $this->order->id,
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        $requestData = [
            'Name'                    => $this->account->getUsername(),
            'Password'                => $this->account->getPassword(),
            'ClientId'                => $this->account->getClientId(),
            'Type'                    => $this->type,
            'IPAddress'               => $this->order->ip,
            'Email'                   => $this->order->email,
            'OrderId'                 => $this->order->id,
            'UserId'                  => isset($this->order->user_id) ? $this->order->user_id : null,
            'Total'                   => $this->order->amount,
            'Currency'                => $this->order->currency,
            'Taksit'                  => $this->order->installment,
            'Number'                  => $responseData['md'],
            'Expires'                 => '',
            'Cvv2Val'                 => '',
            'PayerTxnId'              => $responseData['xid'],
            'PayerSecurityLevel'      => $responseData['eci'],
            'PayerAuthenticationCode' => $responseData['cavv'],
            'CardholderPresentCode'   => '13',
            'Mode'                    => 'P',
            'GroupId'                 => '',
            'TransId'                 => '',
        ];

        if ($this->order->name) {
            $requestData['BillTo'] = [
                'Name' => $this->order->name,
            ];
        }

        if (isset($this->order->recurringFrequency)) {
            $requestData['PbOrder'] = [
                'OrderType'              => 0,
                // Periyodik İşlem Frekansı
                'OrderFrequencyInterval' => $this->order->recurringFrequency,
                //D|M|Y
                'OrderFrequencyCycle'    => $this->order->recurringFrequencyType,
                'TotalNumberPayments'    => $this->order->recurringInstallmentCount,
            ];
        }

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createStatusXML()
    {
        $requestData = [
            'Name'     => $this->account->getUsername(),
            'Password' => $this->account->getPassword(),
            'ClientId' => $this->account->getClientId(),
            'OrderId'  => $this->order->id,
            'Extra'    => [
                $this->types[self::TX_STATUS] => 'QUERY',
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {
        $requestData = [
            'Name'     => $this->account->getUsername(),
            'Password' => $this->account->getPassword(),
            'ClientId' => $this->account->getClientId(),
            'OrderId'  => $this->order->id,
            'Extra'    => [
                $this->types[self::TX_HISTORY] => 'QUERY',
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {
        $requestData = [
            'Name'     => $this->account->getUsername(),
            'Password' => $this->account->getPassword(),
            'ClientId' => $this->account->getClientId(),
            'OrderId'  => $this->order->id,
            'Type'     => $this->types[self::TX_CANCEL],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {
        $requestData = [
            'Name'     => $this->account->getUsername(),
            'Password' => $this->account->getPassword(),
            'ClientId' => $this->account->getClientId(),
            'OrderId'  => $this->order->id,
            'Type'     => $this->types[self::TX_REFUND],
        ];

        if (isset($this->order->amount)) {
            $requestData['Total'] = $this->order->amount;
        }

        return $this->createXML($requestData);
    }

    /**
     * Get ProcReturnCode
     *
     * @return string|null
     */
    protected function getProcReturnCode()
    {
        return isset($this->data->ProcReturnCode) ? (string) $this->data->ProcReturnCode : null;
    }

    /**
     * Get Status Detail Text
     *
     * @return string|null
     */
    protected function getStatusDetail()
    {
        $procReturnCode = $this->getProcReturnCode();

        return $procReturnCode ? (isset($this->codes[$procReturnCode]) ? (string) $this->codes[$procReturnCode] : null) : null;
    }

    /**
     * @inheritDoc
     */
    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $transactionSecurity = 'MPI fallback';
        if ($this->getProcReturnCode() === '00') {
            if ($raw3DAuthResponseData['mdStatus'] == '1') {
                $transactionSecurity = 'Full 3D Secure';
            } elseif (in_array($raw3DAuthResponseData['mdStatus'], [2, 3, 4])) {
                $transactionSecurity = 'Half 3D Secure';
            }
        }

        $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);

        $threeDResponse = [
            'transaction_security' => $transactionSecurity,
            'md_status'            => $raw3DAuthResponseData['mdStatus'],
            'hash'                 => (string) $raw3DAuthResponseData['HASH'],
            'rand'                 => (string) $raw3DAuthResponseData['rnd'],
            'hash_params'          => (string) $raw3DAuthResponseData['HASHPARAMS'],
            'hash_params_val'      => (string) $raw3DAuthResponseData['HASHPARAMSVAL'],
            'masked_number'        => (string) $raw3DAuthResponseData['maskedCreditCard'],
            'month'                => (string) $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Month'],
            'year'                 => (string) $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Year'],
            'amount'               => (string) $raw3DAuthResponseData['amount'],
            'currency'             => (string) $raw3DAuthResponseData['currency'],
            'eci'                  => (string) $raw3DAuthResponseData['eci'],
            'tx_status'            => null,
            'cavv'                 => (string) $raw3DAuthResponseData['cavv'],
            'xid'                  => (string) $raw3DAuthResponseData['oid'],
            'md_error_message'     => (string) $raw3DAuthResponseData['mdErrorMsg'],
            'name'                 => (string) $raw3DAuthResponseData['firmaadi'],
            '3d_all'               => $raw3DAuthResponseData,
        ];

        return (object) array_merge($threeDResponse, $paymentResponseData);
    }

    /**
     * @inheritDoc
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        $status = 'declined';

        if ($this->check3DHash($raw3DAuthResponseData) && $raw3DAuthResponseData['ProcReturnCode'] === '00') {
            if (in_array($raw3DAuthResponseData['mdStatus'], [1, 2, 3, 4])) {
                $status = 'approved';
            }
        }

        $transactionSecurity = 'MPI fallback';
        if ('approved' === $status) {
            if ($raw3DAuthResponseData['mdStatus'] == '1') {
                $transactionSecurity = 'Full 3D Secure';
            } elseif (in_array($raw3DAuthResponseData['mdStatus'], [2, 3, 4])) {
                $transactionSecurity = 'Half 3D Secure';
            }
        }

        return (object) [
            'id'                   => $raw3DAuthResponseData['AuthCode'],
            'trans_id'             => $raw3DAuthResponseData['TransId'],
            'auth_code'            => $raw3DAuthResponseData['AuthCode'],
            'host_ref_num'         => $raw3DAuthResponseData['HostRefNum'],
            'response'             => $raw3DAuthResponseData['Response'],
            'order_id'             => $raw3DAuthResponseData['oid'],
            'transaction_type'     => $this->type,
            'transaction'          => $this->type,
            'transaction_security' => $transactionSecurity,
            'code'                 => $raw3DAuthResponseData['ProcReturnCode'],
            'md_status'            => $raw3DAuthResponseData['mdStatus'],
            'status'               => $status,
            'status_detail'        => isset($this->codes[$raw3DAuthResponseData['ProcReturnCode']]) ? $raw3DAuthResponseData['ProcReturnCode'] : null,
            'hash'                 => $raw3DAuthResponseData['HASH'],
            'rand'                 => $raw3DAuthResponseData['rnd'],
            'hash_params'          => $raw3DAuthResponseData['HASHPARAMS'],
            'hash_params_val'      => $raw3DAuthResponseData['HASHPARAMSVAL'],
            'masked_number'        => $raw3DAuthResponseData['maskedCreditCard'],
            'month'                => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Month'],
            'year'                 => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Year'],
            'amount'               => $raw3DAuthResponseData['amount'],
            'currency'             => $raw3DAuthResponseData['currency'],
            'tx_status'            => null,
            'eci'                  => $raw3DAuthResponseData['eci'],
            'cavv'                 => $raw3DAuthResponseData['cavv'],
            'xid'                  => $raw3DAuthResponseData['oid'],
            'error_code'           => isset($raw3DAuthResponseData['ErrMsg']) ? $raw3DAuthResponseData['ProcReturnCode'] : null,
            'error_message'        => $raw3DAuthResponseData['ErrMsg'],
            'md_error_message'     => $raw3DAuthResponseData['mdErrorMsg'],
            'name'                 => $raw3DAuthResponseData['firmaadi'],
            'email'                => $raw3DAuthResponseData['Email'],
            'campaign_url'         => null,
            'all'                  => $raw3DAuthResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapRefundResponse($rawResponseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object) [
            'order_id'         => isset($rawResponseData->OrderId) ? $rawResponseData->OrderId : null,
            'group_id'         => isset($rawResponseData->GroupId) ? $rawResponseData->GroupId : null,
            'response'         => isset($rawResponseData->Response) ? $rawResponseData->Response : null,
            'auth_code'        => isset($rawResponseData->AuthCode) ? $rawResponseData->AuthCode : null,
            'host_ref_num'     => isset($rawResponseData->HostRefNum) ? $rawResponseData->HostRefNum : null,
            'proc_return_code' => isset($rawResponseData->ProcReturnCode) ? $rawResponseData->ProcReturnCode : null,
            'trans_id'         => isset($rawResponseData->TransId) ? $rawResponseData->TransId : null,
            'error_code'       => isset($rawResponseData->Extra->ERRORCODE) ? $rawResponseData->Extra->ERRORCODE : null,
            'error_message'    => isset($rawResponseData->ErrMsg) ? $rawResponseData->ErrMsg : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapCancelResponse($rawResponseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        $this->response = (object) [
            'order_id'         => isset($rawResponseData->OrderId) ? $rawResponseData->OrderId : null,
            'group_id'         => isset($rawResponseData->GroupId) ? $rawResponseData->GroupId : null,
            'response'         => isset($rawResponseData->Response) ? $rawResponseData->Response : null,
            'auth_code'        => isset($rawResponseData->AuthCode) ? $rawResponseData->AuthCode : null,
            'host_ref_num'     => isset($rawResponseData->HostRefNum) ? $rawResponseData->HostRefNum : null,
            'proc_return_code' => isset($rawResponseData->ProcReturnCode) ? $rawResponseData->ProcReturnCode : null,
            'trans_id'         => isset($rawResponseData->TransId) ? $rawResponseData->TransId : null,
            'error_code'       => isset($rawResponseData->Extra->ERRORCODE) ? $rawResponseData->Extra->ERRORCODE : null,
            'error_message'    => isset($rawResponseData->ErrMsg) ? $rawResponseData->ErrMsg : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapStatusResponse($rawResponseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        $firstAmount = isset($rawResponseData->Extra->ORIG_TRANS_AMT) ? $this->printData($rawResponseData->Extra->ORIG_TRANS_AMT) : null;
        $captureAmount = isset($rawResponseData->Extra->CAPTURE_AMT) ? $this->printData($rawResponseData->Extra->CAPTURE_AMT) : null;
        $capture = $firstAmount === $captureAmount ? true : false;

        return (object) [
            'order_id'         => isset($rawResponseData->OrderId) ? $this->printData($rawResponseData->OrderId) : null,
            'response'         => isset($rawResponseData->Response) ? $this->printData($rawResponseData->Response) : null,
            'proc_return_code' => isset($rawResponseData->ProcReturnCode) ? $this->printData($rawResponseData->ProcReturnCode) : null,
            'trans_id'         => isset($rawResponseData->TransId) ? $this->printData($rawResponseData->TransId) : null,
            'error_message'    => isset($rawResponseData->ErrMsg) ? $this->printData($rawResponseData->ErrMsg) : null,
            'host_ref_num'     => isset($rawResponseData->Extra->HOST_REF_NUM) ? $this->printData($rawResponseData->Extra->HOST_REF_NUM) : null,
            'order_status'     => isset($rawResponseData->Extra->ORDERSTATUS) ? $this->printData($rawResponseData->Extra->ORDERSTATUS) : null,
            'process_type'     => isset($rawResponseData->Extra->CHARGE_TYPE_CD) ? $this->printData($rawResponseData->Extra->CHARGE_TYPE_CD) : null,
            'pan'              => isset($rawResponseData->Extra->PAN) ? $this->printData($rawResponseData->Extra->PAN) : null,
            'num_code'         => isset($rawResponseData->Extra->NUMCODE) ? $this->printData($rawResponseData->Extra->NUMCODE) : null,
            'first_amount'     => $firstAmount,
            'capture_amount'   => $captureAmount,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'capture'          => $capture,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapPaymentResponse($responseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return [
            'id'               => isset($responseData->AuthCode) ? $this->printData($responseData->AuthCode) : null,
            'order_id'         => isset($responseData->OrderId) ? $this->printData($responseData->OrderId) : null,
            'group_id'         => isset($responseData->GroupId) ? $this->printData($responseData->GroupId) : null,
            'trans_id'         => isset($responseData->TransId) ? $this->printData($responseData->TransId) : null,
            'response'         => isset($responseData->Response) ? $this->printData($responseData->Response) : null,
            'transaction_type' => $this->type,
            'transaction'      => $this->type,
            'auth_code'        => isset($responseData->AuthCode) ? $this->printData($responseData->AuthCode) : null,
            'host_ref_num'     => isset($responseData->HostRefNum) ? $this->printData($responseData->HostRefNum) : null,
            'proc_return_code' => isset($responseData->ProcReturnCode) ? $this->printData($responseData->ProcReturnCode) : null,
            'code'             => isset($responseData->ProcReturnCode) ? $this->printData($responseData->ProcReturnCode) : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => isset($responseData->Extra->ERRORCODE) ? $this->printData($responseData->Extra->ERRORCODE) : null,
            'error_message'    => isset($responseData->Extra->ERRORCODE) ? $this->printData($responseData->ErrMsg) : null,
            'campaign_url'     => null,
            'extra'            => isset($responseData->Extra) ? $responseData->Extra : null,
            'all'              => $responseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapHistoryResponse($rawResponseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object) [
            'order_id'         => isset($rawResponseData->OrderId) ? $this->printData($rawResponseData->OrderId) : null,
            'response'         => isset($rawResponseData->Response) ? $this->printData($rawResponseData->Response) : null,
            'proc_return_code' => isset($rawResponseData->ProcReturnCode) ? $this->printData($rawResponseData->ProcReturnCode) : null,
            'error_message'    => isset($rawResponseData->ErrMsg) ? $this->printData($rawResponseData->ErrMsg) : null,
            'num_code'         => isset($rawResponseData->Extra->NUMCODE) ? $this->printData($rawResponseData->Extra->NUMCODE) : null,
            'trans_count'      => isset($rawResponseData->Extra->TRXCOUNT) ? $this->printData($rawResponseData->Extra->TRXCOUNT) : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'all'              => $rawResponseData,
        ];
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

        if (isset($order['recurringFrequency'])) {
            $order['recurringFrequencyType'] = $this->mapRecurringFrequency($order['recurringFrequencyType']);
        }

        // Order
        return (object) array_merge($order, [
            'installment' => $installment,
            'currency'    => $this->mapCurrency($order['currency']),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order)
    {
        return (object) [
            'id' => $order['id'],
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

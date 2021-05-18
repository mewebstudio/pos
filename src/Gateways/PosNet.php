<?php

namespace Mews\Pos\Gateways;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardPosNet;
use Mews\Pos\Exceptions\NotImplementedException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PosNet
 */
class PosNet extends AbstractGateway
{
    const LANG_TR = 'tr';
    const LANG_EN = 'en';

    /**
     * PosNet requires order id with specific length
     */
    private const ORDER_ID_LENGTH = 20;
    /**
     * order Id total length including prefix;
     */
    private const ORDER_ID_TOTAL_LENGTH = 24;
    private const ORDER_ID_3D_PREFIX = 'TDSC';
    private const ORDER_ID_3D_PAY_PREFIX = '';  //?
    private const ORDER_ID_REGULAR_PREFIX = '';  //?

    /**
     * @const string
     */
    public const NAME = 'PosNet';

    /**
     * Response Codes
     *
     * @var array
     */
    protected $codes = [
        '0'    => 'declined',
        '1'    => 'approved',
        '2'    => 'declined',
        '00'   => 'approved',
        '0001' => 'bank_call',
        '0005' => 'reject',
        '0007' => 'bank_call',
        '0012' => 'reject',
        '0014' => 'reject',
        '0030' => 'bank_call',
        '0041' => 'reject',
        '0043' => 'reject',
        '0051' => 'reject',
        '0053' => 'bank_call',
        '0054' => 'reject',
        '0057' => 'reject',
        '0058' => 'reject',
        '0062' => 'reject',
        '0065' => 'reject',
        '0091' => 'bank_call',
        '0123' => 'transaction_not_found',
        '0444' => 'bank_call',
    ];

    /**
     * Transaction Types
     *
     * @var array
     */
    protected $types = [
        self::TX_PAY      => 'Sale',
        self::TX_PRE_PAY  => 'Auth',
        self::TX_POST_PAY => 'Capt',
        self::TX_CANCEL   => 'reverse',
        self::TX_REFUND   => 'return',
        self::TX_STATUS   => 'agreement',
    ];

    /**
     * Fixed Currencies
     * @var array
     */
    protected $currencies = [
        'TRY' => 'TL',
        'USD' => 'US',
        'EUR' => 'EU',
        'GBP' => 'GB',
        'JPY' => 'JP',
        'RUB' => 'RU',
    ];

    /**
     * API Account
     *
     * @var PosNetAccount
     */
    protected $account = [];

    /**
     * @var CreditCardPosNet|null
     */
    protected $card;

    /**
     * Request
     *
     * @var Request
     */
    protected $request;

    /**
     * @var PosNetCrypt|null
     */
    public $crypt;

    /**
     * PosNet constructor.
     *
     * @param array         $config
     * @param PosNetAccount $account
     * @param array         $currencies
     */
    public function __construct($config, $account, array $currencies)
    {
        $this->crypt = new PosNetCrypt();
        parent::__construct($config, $account, $currencies);
    }

    /**
     * @inheritDoc
     */
    public function createXML(array $data, $encoding = 'ISO-8859-9'): string
    {
        return parent::createXML(['posnetRequest' => $data], $encoding);
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment()
    {
        throw new NotImplementedException();
    }

    /**
     * Get OOS transaction data
     *
     * @return object
     *
     * @throws GuzzleException
     */
    public function getOosTransactionData()
    {
        if (null === $this->card->getHolderName() && isset($this->order->name)) {
            $this->card->setHolderName($this->order->name);
        }

        $requestData = [
            'mid'            => $this->account->getClientId(),
            'tid'            => $this->account->getTerminalId(),
            'oosRequestData' => [
                'posnetid'       => $this->account->getPosNetId(),
                'ccno'           => $this->card->getNumber(),
                'expDate'        => $this->card->getExpirationDate(),
                'cvc'            => $this->card->getCvv(),
                'amount'         => $this->order->amount,
                'currencyCode'   => $this->order->currency,
                'installment'    => $this->order->installment,
                'XID'            => self::formatOrderId($this->order->id),
                'cardHolderName' => $this->card->getHolderName(),
                'tranType'       => $this->type,
            ],
        ];
        $xml = $this->createXML($requestData);

        $this->send($xml);

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment()
    {
        $request = Request::createFromGlobals();

        if ($this->check3DHash($request->request->all())) {
            $contents = $this->create3DResolveMerchantDataXML($request->request->all());
            $this->send($contents);
        } else {
            goto end;
        }

        if ($this->getProcReturnCode() !== '00') {
            goto end;
        }

        if (!$this->verifyResponseMAC($this->data->oosResolveMerchantDataResponse)) {
            goto end;
        }

        if ($this->getProcReturnCode() === '00' && $this->getStatusDetail() === 'approved') {
            //if 3D Authentication is successful:
            if (in_array($this->data->oosResolveMerchantDataResponse->mdStatus, [1, 2, 3, 4])) {
                $contents = $this->create3DPaymentXML($request->request->all());
                $this->send($contents);
            }
        }

        end:
        $this->response = $this->map3DPaymentData($request->request->all(), $this->data);

        return $this;
    }

    /**
     * Make 3D Pay Payment
     *
     * @return $this
     */
    public function make3DPayPayment()
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData()
    {
        if (!$this->card || !$this->order) {
            return [];
        }

        $data = $this->getOosTransactionData();

        $inputs = [
            'posnetData'        => $data->oosRequestDataResponse->data1,
            'posnetData2'       => $data->oosRequestDataResponse->data2,
            'mid'               => $this->account->getClientId(),
            'posnetID'          => $this->account->getPosNetId(),
            'digest'            => $data->oosRequestDataResponse->sign,
            'vftCode'           => isset($this->account->promotion_code) ? $this->account->promotion_code : null,
            'merchantReturnURL' => $this->order->success_url,
            'url'               => '',
            'lang'              => $this->getLang(),
        ];

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

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $response = $client->request('POST', $this->getApiURL(), [
            'headers' => $headers,
            'body'    => "xmldata=$contents",
        ]);

        $this->data = $this->XMLStringToObject($response->getBody()->getContents());

        return $this;
    }

    /**
     * @return PosNetAccount
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @return CreditCardPosNet|null
     */
    public function getCard()
    {
        return $this->card;
    }

    /**
     * @param CreditCardPosNet|null $card
     */
    public function setCard($card)
    {
        $this->card = $card;
    }

    /**
     * Hash string
     *
     * @param string $str
     *
     * @return string
     */
    public function hashString(string $str)
    {
        return base64_encode(hash('sha256', $str, true));
    }

    /**
     * Create 3D Hash (MAC)
     *
     * @return string
     */
    public function create3DHash()
    {
        $hashStr = '';
        $glue = ';';
        $firstHash = $this->hashString($this->account->getStoreKey().$glue.$this->account->getTerminalId());

        if ($this->account->getModel() === '3d' || $this->account->getModel() === '3d_pay') {
            $hashStr = $this->hashString(implode($glue, [self::formatOrderId($this->order->id), $this->order->amount, $this->order->currency, $this->account->getClientId(), $firstHash]));
        }

        return $hashStr;
    }

    /**
     * verifies the if request came from bank
     *
     * @param mixed $data oosResolveMerchantDataResponse
     *
     * @return boolean
     */
    public function verifyResponseMAC($data)
    {
        $hashStr = '';
        $glue = ';';
        $firstHash = $this->hashString($this->account->getStoreKey().$glue.$this->account->getTerminalId());

        if ($this->account->getModel() === '3d' || $this->account->getModel() === '3d_pay') {
            $hashStr = $this->hashString(implode($glue, [$data->mdStatus, self::formatOrderId($this->order->id), $this->order->amount, $this->order->currency, $this->account->getClientId(), $firstHash]));
        }

        return $hashStr === $data->mac;
    }

    /**
     * formats order id by adding 0 pad to the left
     *
     * @param     $orderId
     * @param int $padLength
     *
     * @return string
     */
    public static function formatOrderId($orderId, int $padLength = null)
    {
        if (null === $padLength) {
            $padLength = self::ORDER_ID_LENGTH;
        }

        return str_pad($orderId, $padLength, '0', STR_PAD_LEFT);
    }

    /**
     * Get amount
     * formats 10.01 to 1001
     *
     * @param float $amount
     *
     * @return int
     */
    public static function amountFormat($amount): int
    {
        return round($amount, 2) * 100;
    }

    /**
     * Get PrefixedOrderId
     * To check the status of an order or cancel/refund order Yapikredi
     * - requires the order length to be 24
     * - and order id prefix which is "TDSC" for 3D payments
     *
     * @param string $orderId
     * @param string $accountModel
     *
     * @return string
     */
    public static function mapOrderIdToPrefixedOrderId(string $orderId, string $accountModel)
    {
        $prefix = self::ORDER_ID_REGULAR_PREFIX;
        if ('3d' === $accountModel) {
            $prefix = self::ORDER_ID_3D_PREFIX;
        } elseif ('3d_pay' === $accountModel) {
            $prefix = self::ORDER_ID_3D_PAY_PREFIX;
        }

        return $prefix.self::formatOrderId($orderId, self::ORDER_ID_TOTAL_LENGTH - strlen($prefix));
    }


    /**
     * formats installment in 00, 02, 06 format
     *
     * @param int|string $installment
     *
     * @return string
     */
    public static function formatInstallment($installment)
    {
        if ($installment > 1) {
            return str_pad($installment, 2, '0', STR_PAD_LEFT);
        }

        return '00';
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML()
    {
        $requestData = [
            'mid'                   => $this->account->getClientId(),
            'tid'                   => $this->account->getTerminalId(),
            'tranDateRequired'      => '1',
            strtolower($this->type) => [
                'orderID'      => self::formatOrderId($this->order->id),
                'installment'  => $this->order->installment,
                'amount'       => $this->order->amount,
                'currencyCode' => $this->order->currency,
                'ccno'         => $this->card->getNumber(),
                'expDate'      => $this->card->getExpirationDate(),
                'cvc'          => $this->card->getCvv(),
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
            'mid'                                       => $this->account->getClientId(),
            'tid'                                       => $this->account->getTerminalId(),
            'tranDateRequired'                          => '1',
            strtolower($this->types[self::TX_POST_PAY]) => [
                'hostLogKey'   => $this->order->host_ref_num,
                'amount'       => $this->order->amount,
                'currencyCode' => $this->order->currency,
                'installment'  => $this->order->installment,
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        $requestData = [
            'mid'         => $this->account->getClientId(),
            'tid'         => $this->account->getTerminalId(),
            'oosTranData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
                'wpAmount'     => 0,
                'mac'          => $this->create3DHash(),
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @param $responseData
     *
     * @return string
     */
    public function create3DResolveMerchantDataXML($responseData)
    {
        $requestData = [
            'mid'                    => $this->account->getClientId(),
            'tid'                    => $this->account->getTerminalId(),
            'oosResolveMerchantData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
                'mac'          => $this->create3DHash(),
            ],
        ];

        return $this->createXML($requestData);
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
        $requestData = [
            'mid'                         => $this->account->getClientId(),
            'tid'                         => $this->account->getTerminalId(),
            $this->types[self::TX_STATUS] => [
                'orderID' => $this->order->id,
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
            'mid'                         => $this->account->getClientId(),
            'tid'                         => $this->account->getTerminalId(),
            'tranDateRequired'            => '1',
            $this->types[self::TX_CANCEL] => [
                'transaction' => 'sale',
            ],
        ];

        if (isset($this->order->auth_code)) {
            $requestData[$this->types[self::TX_CANCEL]]['authCode'] = $this->order->auth_code;
        }

        //either will work
        if (isset($this->order->host_ref_num)) {
            $requestData[$this->types[self::TX_CANCEL]]['hostLogKey'] = $this->order->host_ref_num;
        } else {
            $requestData[$this->types[self::TX_CANCEL]]['orderID'] = $this->order->id;
        }

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {
        $requestData = [
            'mid'                         => $this->account->getClientId(),
            'tid'                         => $this->account->getTerminalId(),
            'tranDateRequired'            => '1',
            $this->types[self::TX_REFUND] => [
                'amount'       => $this->order->amount,
                'currencyCode' => $this->order->currency,
            ],
        ];

        if (isset($this->order->host_ref_num)) {
            $requestData[$this->types[self::TX_REFUND]]['hostLogKey'] = $this->order->host_ref_num;
        } else {
            $requestData[$this->types[self::TX_REFUND]]['orderID'] = $this->order->id;
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
        return (string) $this->data->approved == '1' ? '00' : $this->data->approved;
    }

    /**
     * Get Status Detail Text
     *
     * @return string|null
     */
    protected function getStatusDetail()
    {
        $procReturnCode = $this->getProcReturnCode();

        return isset($this->codes[$procReturnCode]) ? (string) $this->codes[$procReturnCode] : null;
    }


    /**
     * Check 3D Hash
     *
     * @param array $data
     *
     * @return bool
     */
    protected function check3DHash(array $data)
    {
        if ($this->crypt instanceof PosNetCrypt) {
            $decryptedString = $this->crypt->decrypt($data['MerchantPacket'], $this->account->getStoreKey());

            $decryptedData = explode(';', $decryptedString);

            $originalData = array_map('strval', [
                $this->account->getClientId(),
                $this->account->getTerminalId(),
                $this->order->amount,
                ((int) $this->order->installment),
                self::formatOrderId($this->order->id),
            ]);

            $decryptedDataList = array_map('strval', [
                $decryptedData[0],
                $decryptedData[1],
                $decryptedData[2],
                ((int) $decryptedData[3]),
                $decryptedData[4],
            ]);

            return $originalData === $decryptedDataList;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $status = 'declined';
        $transactionSecurity = '';
        if ($this->getProcReturnCode() === '00' && $this->getStatusDetail() === 'approved') {
            if ($rawPaymentResponseData->oosResolveMerchantDataResponse->mdStatus == '1') {
                $transactionSecurity = 'Full 3D Secure';
                $status = 'approved';
            } elseif (in_array($rawPaymentResponseData->oosResolveMerchantDataResponse->mdStatus, [2, 3, 4])) {
                $transactionSecurity = 'Half 3D Secure';
                $status = 'approved';
            }
        }

        if ($rawPaymentResponseData->approved != 1) {
            $status = 'declined';
        }

        return (object) [
            'id'                   => isset($rawPaymentResponseData->authCode) ? $this->printData($rawPaymentResponseData->authCode) : null,
            'order_id'             => isset($this->order->id) ? $this->printData($this->order->id) : null,
            'group_id'             => isset($rawPaymentResponseData->groupID) ? $this->printData($rawPaymentResponseData->groupID) : null,
            'trans_id'             => isset($rawPaymentResponseData->authCode) ? $this->printData($rawPaymentResponseData->authCode) : null,
            'response'             => $this->getStatusDetail(),
            'transaction_type'     => $this->type,
            'transaction'          => $this->type,
            'transaction_security' => $transactionSecurity,
            'auth_code'            => isset($rawPaymentResponseData->authCode) ? $this->printData($rawPaymentResponseData->authCode) : null,
            'host_ref_num'         => isset($rawPaymentResponseData->hostlogkey) ? $this->printData($rawPaymentResponseData->hostlogkey) : null,
            'ret_ref_num'          => isset($rawPaymentResponseData->transaction->hostlogkey) ? $this->printData($rawPaymentResponseData->transaction->hostlogkey) : null,
            'proc_return_code'     => $this->getProcReturnCode(),
            'code'                 => $this->getProcReturnCode(),
            'status'               => $status,
            'status_detail'        => $this->getStatusDetail(),
            'error_code'           => !empty($rawPaymentResponseData->respCode) ? $this->printData($rawPaymentResponseData->respCode) : null,
            'error_message'        => !empty($rawPaymentResponseData->respText) ? $this->printData($rawPaymentResponseData->respText) : null,
            'md_status'            => isset($rawPaymentResponseData->oosResolveMerchantDataResponse->mdStatus) ? $this->printData($rawPaymentResponseData->oosResolveMerchantDataResponse->mdStatus) : null,
            'hash'                 => [
                'merchant_packet' => $raw3DAuthResponseData['MerchantPacket'],
                'bank_packet'     => $raw3DAuthResponseData['BankPacket'],
                'sign'            => $raw3DAuthResponseData['Sign'],
            ],
            'xid'                  => isset($rawPaymentResponseData->oosResolveMerchantDataResponse->xid) ? $rawPaymentResponseData->oosResolveMerchantDataResponse->xid : null,
            'md_error_message'     => isset($rawPaymentResponseData->oosResolveMerchantDataResponse->mdErrorMessage) ? $rawPaymentResponseData->oosResolveMerchantDataResponse->mdErrorMessage : null,
            'campaign_url'         => null,
            'all'                  => $rawPaymentResponseData,
            '3d_all'               => $raw3DAuthResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    protected function mapPaymentResponse($responseData)
    {
        $status = 'declined';
        $code = '1';
        $procReturnCode = '01';
        $errorCode = !empty($responseData->respCode) ? $responseData->respCode : null;

        if ($this->getProcReturnCode() === '00' && $this->getStatusDetail() === 'approved' && $responseData && !$errorCode) {
            $status = 'approved';
            $code = isset($responseData->approved) ? $responseData->approved : null;
            $procReturnCode = $this->getProcReturnCode();
        }

        return (object) [
            'id'               => isset($responseData->authCode) ? $this->printData($responseData->authCode) : null,
            'order_id'         => $this->order->id,
            'fixed_order_id'   => self::formatOrderId($this->order->id),
            'group_id'         => isset($responseData->groupID) ? $this->printData($responseData->groupID) : null,
            'trans_id'         => isset($responseData->authCode) ? $this->printData($responseData->authCode) : null,
            'response'         => $this->getStatusDetail(),
            'transaction_type' => $this->type,
            'transaction'      => $this->type,
            'auth_code'        => isset($responseData->authCode) ? $this->printData($responseData->authCode) : null,
            'host_ref_num'     => isset($responseData->hostlogkey) ? $this->printData($responseData->hostlogkey) : null,
            'ret_ref_num'      => isset($responseData->hostlogkey) ? $this->printData($responseData->hostlogkey) : null,
            'proc_return_code' => $procReturnCode,
            'code'             => $code,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => $errorCode,
            'error_message'    => !empty($responseData->respText) ? $this->printData($responseData->respText) : null,
            'campaign_url'     => null,
            'extra'            => null,
            'all'              => $responseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapRefundResponse($rawResponseData)
    {
        $status = 'declined';
        $code = '1';
        $procReturnCode = '01';
        $errorCode = !empty($rawResponseData->respCode) ? $rawResponseData->respCode : null;

        if ($this->getProcReturnCode() === '00' && $rawResponseData && !$errorCode) {
            $status = 'approved';
            $code = isset($rawResponseData->approved) ? $rawResponseData->approved : null;
            $procReturnCode = $this->getProcReturnCode();
        }

        $transaction = null;
        $transactionType = null;
        $state = isset($rawResponseData->state) ? $rawResponseData->state : null;
        if ('Sale' === $state) {
            $transaction = 'pay';
            $transactionType = $this->types[$transaction];
        } elseif ('Authorization' === $state) {
            $transaction = 'pre';
            $transactionType = $this->types[$transaction];
        } elseif ('Capture' === $state) {
            $transaction = 'post';
            $transactionType = $this->types[$transaction];
        }

        return (object) [
            'id'               => isset($rawResponseData->transaction->authCode) ? $this->printData($rawResponseData->transaction->authCode) : null,
            'order_id'         => isset($this->order->id) ? $this->printData($this->order->id) : null,
            'fixed_order_id'   => isset($rawResponseData->transaction->orderID) ? $this->printData($rawResponseData->transaction->orderID) : null,
            'group_id'         => isset($rawResponseData->transaction->groupID) ? $this->printData($rawResponseData->transaction->groupID) : null,
            'trans_id'         => isset($rawResponseData->transaction->authCode) ? $this->printData($rawResponseData->transaction->authCode) : null,
            'response'         => $this->getStatusDetail(),
            'auth_code'        => isset($rawResponseData->transaction->authCode) ? $this->printData($rawResponseData->transaction->authCode) : null,
            'host_ref_num'     => isset($rawResponseData->transaction->hostlogkey) ? $this->printData($rawResponseData->transaction->hostlogkey) : null,
            'ret_ref_num'      => isset($rawResponseData->transaction->hostlogkey) ? $this->printData($rawResponseData->transaction->hostlogkey) : null,
            'transaction'      => $transaction,
            'transaction_type' => $transactionType,
            'state'            => $state,
            'date'             => isset($rawResponseData->transaction->tranDate) ? $this->printData($rawResponseData->transaction->tranDate) : null,
            'proc_return_code' => $procReturnCode,
            'code'             => $code,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => $errorCode,
            'error_message'    => !empty($rawResponseData->respText) ? $this->printData($rawResponseData->respText) : null,
            'extra'            => null,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapCancelResponse($rawResponseData)
    {
        return $this->mapRefundResponse($rawResponseData);
    }

    /**
     * @inheritDoc
     */
    protected function mapStatusResponse($rawResponseData)
    {
        $status = 'declined';
        $code = '1';
        $procReturnCode = '01';
        $errorCode = !empty($rawResponseData->respCode) ? $rawResponseData->respCode : null;

        if ($this->getProcReturnCode() === '00' && isset($rawResponseData->transactions) && !$errorCode) {
            $status = 'approved';
            $code = isset($rawResponseData->transactions->approved) ? $rawResponseData->transactions->approved : null;
            $procReturnCode = $this->getProcReturnCode();
        }

        $transaction = null;
        $transactionType = null;

        $state = null;
        $authCode = null;
        if (isset($rawResponseData->transactions->transaction)) {
            $state = isset($rawResponseData->transactions->transaction->state) ?
                $rawResponseData->transactions->transaction->state :
                null;

            $authCode = isset($rawResponseData->transactions->transaction->authCode) ? $this->printData($rawResponseData->transactions->transaction->authCode) : null;

            if (is_array($rawResponseData->transactions->transaction) && count($rawResponseData->transactions->transaction)) {
                $state = $rawResponseData->transactions->transaction[0]->state;
                $authCode = $rawResponseData->transactions->transaction[0]->authCode;
            }
        }

        if ('Sale' === $state) {
            $transaction = 'pay';
            $state = $transaction;
            $transactionType = $this->types[$transaction];
        } elseif ('Authorization' === $state) {
            $transaction = 'pre';
            $state = $transaction;
            $transactionType = $this->types[$transaction];
        } elseif ('Capture' === $state) {
            $transaction = 'post';
            $state = $transaction;
            $transactionType = $this->types[$transaction];
        } elseif ('Bonus_Reverse' === $state) {
            $state = 'cancel';
        } else {
            $state = 'mixed';
        }

        return (object) [
            'id'               => $authCode,
            'order_id'         => isset($this->order->id) ? $this->printData($this->order->id) : null,
            'fixed_order_id'   => self::formatOrderId($this->order->id),
            'group_id'         => isset($rawResponseData->transactions->transaction->groupID) ? $this->printData($rawResponseData->transactions->transaction->groupID) : null,
            'trans_id'         => $authCode,
            'response'         => $this->getStatusDetail(),
            'auth_code'        => $authCode,
            'host_ref_num'     => isset($rawResponseData->transactions->transaction->hostLogKey) ? $this->printData($rawResponseData->transactions->transaction->hostLogKey) : null,
            'ret_ref_num'      => null,
            'transaction'      => $transaction,
            'transaction_type' => $transactionType,
            'state'            => $state,
            'date'             => isset($rawResponseData->transactions->transaction->tranDate) ? $this->printData($rawResponseData->transactions->transaction->tranDate) : null,
            'proc_return_code' => $procReturnCode,
            'code'             => $code,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => $errorCode,
            'error_message'    => !empty($rawResponseData->respText) ? $this->printData($rawResponseData->respText) : null,
            'extra'            => null,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapHistoryResponse($rawResponseData)
    {
        $status = 'declined';
        $code = '1';
        $procReturnCode = '01';
        $errorCode = !empty($rawResponseData->respCode) ? $rawResponseData->respCode : null;

        if ($this->getProcReturnCode() === '00' && isset($rawResponseData->transactions) && !$errorCode) {
            $status = 'approved';
            $code = isset($rawResponseData->transactions->approved) ? $rawResponseData->transactions->approved : null;
            $procReturnCode = $this->getProcReturnCode();
        }

        $transaction = null;
        $transactionType = null;

        $state = null;
        $authCode = null;
        $refunds = [];
        if (isset($rawResponseData->transactions->transaction)) {
            $state = isset($rawResponseData->transactions->transaction->state) ?
                $rawResponseData->transactions->transaction->state :
                null;

            $authCode = isset($rawResponseData->transactions->transaction->authCode) ? $this->printData($rawResponseData->transactions->transaction->authCode) : null;

            if (is_array($rawResponseData->transactions->transaction) && count($rawResponseData->transactions->transaction)) {
                $state = $rawResponseData->transactions->transaction[0]->state;
                $authCode = $rawResponseData->transactions->transaction[0]->authCode;

                if (count($rawResponseData->transactions->transaction) > 1) {
                    $currencies = array_flip($this->currencies);

                    foreach ($rawResponseData->transactions->transaction as $key => $_transaction) {
                        if ($key > 0) {
                            $currency = isset($currencies[$_transaction->currencyCode]) ?
                                (string) $currencies[$_transaction->currencyCode] :
                                $_transaction->currencyCode;
                            $refunds[] = [
                                'amount'    => (float) $_transaction->amount,
                                'currency'  => $currency,
                                'auth_code' => $_transaction->authCode,
                                'date'      => $_transaction->tranDate,
                            ];
                        }
                    }
                }
            }
        }

        if ('Sale' === $state) {
            $transaction = 'pay';
            $state = $transaction;
            $transactionType = $this->types[$transaction];
        } elseif ('Authorization' === $state) {
            $transaction = 'pre';
            $state = $transaction;
            $transactionType = $this->types[$transaction];
        } elseif ('Capture' === $state) {
            $transaction = 'post';
            $state = $transaction;
            $transactionType = $this->types[$transaction];
        } elseif ('Bonus_Reverse' === $state) {
            $state = 'cancel';
        } else {
            $state = 'mixed';
        }

        return (object) [
            'id'               => $authCode,
            'order_id'         => isset($this->order->id) ? $this->printData($this->order->id) : null,
            'group_id'         => isset($rawResponseData->transactions->transaction->groupID) ? $this->printData($rawResponseData->transactions->transaction->groupID) : null,
            'trans_id'         => $authCode,
            'response'         => $this->getStatusDetail(),
            'auth_code'        => $authCode,
            'host_ref_num'     => isset($rawResponseData->transactions->transaction->hostLogKey) ? $this->printData($rawResponseData->transactions->transaction->hostLogKey) : null,
            'ret_ref_num'      => null,
            'transaction'      => $transaction,
            'transaction_type' => $transactionType,
            'state'            => $state,
            'date'             => isset($rawResponseData->transactions->transaction->tranDate) ? $this->printData($rawResponseData->transactions->transaction->tranDate) : null,
            'refunds'          => $refunds,
            'proc_return_code' => $procReturnCode,
            'code'             => $code,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => $errorCode,
            'error_message'    => !empty($rawResponseData->respText) ? $this->printData($rawResponseData->respText) : null,
            'extra'            => null,
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
            $installment = $order['installment'];
        }

        return (object) array_merge($order, [
            'id'          => self::formatOrderId($order['id']),
            'installment' => self::formatInstallment($installment),
            'amount'      => self::amountFormat($order['amount']),
            'currency'    => $this->mapCurrency($order['currency']),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order)
    {
        // Installment
        $installment = 0;
        if (isset($order['installment']) && $order['installment'] > 1) {
            $installment = $order['installment'];
        }

        return (object) [
            'host_ref_num' => $order['host_ref_num'],
            'amount'       => self::amountFormat($order['amount']),
            'currency'     => $this->mapCurrency($order['currency']),
            'installment'  => self::formatInstallment($installment),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order)
    {
        return (object) [
            'id' => self::mapOrderIdToPrefixedOrderId($order['id'], $this->account->getModel()),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $order)
    {
        return $this->prepareStatusOrder($order);
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order)
    {
        return (object) [
            //id or host_ref_num
            'id'           => isset($order['id']) ? self::mapOrderIdToPrefixedOrderId($order['id'], $this->account->getModel()) : null,
            'host_ref_num' => isset($order['host_ref_num']) ? $order['host_ref_num'] : null,
            //optional
            'auth_code'    => isset($order['auth_code']) ? $order['auth_code'] : null,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order)
    {
        return (object) [
            //id or host_ref_num
            'id'           => isset($order['id']) ? self::mapOrderIdToPrefixedOrderId($order['id'], $this->account->getModel()) : null,
            'host_ref_num' => isset($order['host_ref_num']) ? $order['host_ref_num'] : null,
            'amount'       => self::amountFormat($order['amount']),
            'currency'     => self::mapCurrency($order['currency']),
        ];
    }
}

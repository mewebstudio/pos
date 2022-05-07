<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use GuzzleHttp\Client;
use Mews\Pos\DataMapper\AbstractRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Symfony\Component\HttpFoundation\Request;

/**
 * todo cardType verisi dokumantasyona gore kontrol edilmesi gerekiyor.
 * cardType gondermeden de su an calisiyor.
 * Class EstPos
 */
class EstPos extends AbstractGateway
{
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
     * @var EstPosAccount
     */
    protected $account;

    /**
     * @var AbstractCreditCard|null
     */
    protected $card;

    /**
     * EstPos constructor.
     * @inheritdoc
     *
     * @param EstPosAccount             $account
     */
    public function __construct(array $config, AbstractPosAccount $account, AbstractRequestDataMapper $requestDataMapper)
    {
        parent::__construct($config, $account, $requestDataMapper);
    }

    /**
     * @inheritDoc
     */
    public function createXML(array $nodes, string $encoding = 'ISO-8859-9', bool $ignorePiNode = false): string
    {
        return parent::createXML(['CC5Request' => $nodes], $encoding, $ignorePiNode);
    }

    /**
     * Create 3D Hash
     *
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param string             $txType
     *
     * @return string
     */
    public function create3DHash(AbstractPosAccount $account, $order, string $txType): string
    {
        return $this->requestDataMapper->create3DHash($account, $order, $txType);
    }

    /**
     * Check 3D Hash
     *
     * @param array $data
     *
     * @return bool
     */
    public function check3DHash(array $data): bool
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
        $hash = $this->hashString($hashVal);

        $return = false;
        if ($hashParams && !($paramsVal !== $hashParamsVal || $hashParam !== $hash)) {
            $return = true;
        }

        return $return;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request)
    {
        $provisionResponse = null;
        if ($this->check3DHash($request->request->all())) {
            if ($request->request->get('mdErrorMsg') !== 'Authenticated') {
                /**
                 * TODO hata durumu ele alinmasi gerekiyor
                 * ornegin soyle bir hata donebilir
                 * ["ProcReturnCode" => "99", "mdStatus" => "7", "mdErrorMsg" => "Isyeri kullanim tipi desteklenmiyor.",
                 * "ErrMsg" => "Isyeri kullanim tipi desteklenmiyor.", "Response" => "Error", "ErrCode" => "3D-1007", ...]
                 */
            } else {
                $contents = $this->create3DPaymentXML($request->request->all());
                $provisionResponse = $this->send($contents);
            }
        }

        $this->response = $this->map3DPaymentData($request->request->all(), $provisionResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request)
    {
        $this->response = $this->map3DPayResponseData($request->request->all());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request)
    {
        $this->response = (object) $this->map3DHostResponseData($request->request->all());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(): array
    {
        if (!$this->order) {
            return [];
        }

        return $this->requestDataMapper->create3DFormData($this->account, $this->order, $this->type, $this->get3DGatewayURL(), $this->card);
    }

    /**
     * @inheritDoc
     */
    public function send($contents, ?string $url = null)
    {
        $client = new Client();

        $response = $client->request('POST', $this->getApiURL(), [
            'body' => $contents,
        ]);

        $this->data = $this->XMLStringToObject($response->getBody()->getContents());

        return $this->data;
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
     * @return EstPosAccount
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML()
    {
        $requestData = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $this->order, $this->type, $this->card);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML()
    {
        $requestData = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $this->order);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $this->order, $this->type, $responseData);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createStatusXML()
    {
        $requestData = $this->requestDataMapper->createStatusRequestData($this->account, $this->order);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {
        $requestData = $this->requestDataMapper->createHistoryRequestData($this->account, $this->order, $customQueryData);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {
        $requestData = $this->requestDataMapper->createCancelRequestData($this->account, $this->order);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {
        $requestData = $this->requestDataMapper->createRefundRequestData($this->account, $this->order);

        return $this->createXML($requestData);
    }

    /**
     * Get ProcReturnCode
     *
     * @return string|null
     */
    protected function getProcReturnCode(): ?string
    {
        return isset($this->data->ProcReturnCode) ? (string) $this->data->ProcReturnCode : null;
    }

    /**
     * Get Status Detail Text
     *
     * @return string|null
     */
    protected function getStatusDetail(): ?string
    {
        $procReturnCode = $this->getProcReturnCode();

        return $procReturnCode ? (isset($this->codes[$procReturnCode]) ? (string) $this->codes[$procReturnCode] : null) : null;
    }

    /**
     * @inheritDoc
     */
    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $transactionSecurity = 'MPI fallback';
        if ($this->getProcReturnCode() === '00') {
            if ('1' === $raw3DAuthResponseData['mdStatus']) {
                $transactionSecurity = 'Full 3D Secure';
            } elseif (in_array($raw3DAuthResponseData['mdStatus'], [2, 3, 4])) {
                $transactionSecurity = 'Half 3D Secure';
            }
        }

        $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);

        $threeDResponse = [
            'transaction_security' => $transactionSecurity,
            'md_status'            => $raw3DAuthResponseData['mdStatus'],
            'hash'                 => $raw3DAuthResponseData['HASH'],
            'order_id'             => $raw3DAuthResponseData['oid'],
            'rand'                 => $raw3DAuthResponseData['rnd'],
            'hash_params'          => $raw3DAuthResponseData['HASHPARAMS'],
            'hash_params_val'      => $raw3DAuthResponseData['HASHPARAMSVAL'],
            'masked_number'        => $raw3DAuthResponseData['maskedCreditCard'],
            'month'                => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Month'],
            'year'                 => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Year'],
            'amount'               => $raw3DAuthResponseData['amount'],
            'currency'             => array_search($raw3DAuthResponseData['currency'], $this->requestDataMapper->getCurrencyMappings()),
            'eci'                  => null,
            'tx_status'            => null,
            'cavv'                 => null,
            'xid'                  => $raw3DAuthResponseData['oid'],
            'md_error_message'     => 'Authenticated' !== $raw3DAuthResponseData['mdErrorMsg'] ? $raw3DAuthResponseData['mdErrorMsg'] : null,
            'name'                 => $raw3DAuthResponseData['firmaadi'],
            '3d_all'               => $raw3DAuthResponseData,
        ];

        if ('Authenticated' === $raw3DAuthResponseData['mdErrorMsg']) {
            $threeDResponse['eci'] = $raw3DAuthResponseData['eci'];
            $threeDResponse['cavv'] = $raw3DAuthResponseData['cavv'];
        }

        return (object) $this->mergeArraysPreferNonNullValues($threeDResponse, $paymentResponseData);
    }

    /**
     * @inheritDoc
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        $status = 'declined';

        if ($this->check3DHash($raw3DAuthResponseData) && $raw3DAuthResponseData['mdErrorMsg'] === 'Authenticated') {
            if (in_array($raw3DAuthResponseData['mdStatus'], [1, 2, 3, 4])) {
                $status = 'approved';
            }
        }

        $transactionSecurity = 'MPI fallback';
        if ('approved' === $status) {
            if ('1' === $raw3DAuthResponseData['mdStatus']) {
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
            'transaction'          => empty($this->type) ? null : $this->requestDataMapper->mapTxType($this->type),
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
    protected function map3DHostResponseData($raw3DAuthResponseData)
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $status = 'declined';

        if ($this->check3DHash($raw3DAuthResponseData) && 'Authenticated' === $raw3DAuthResponseData['mdErrorMsg']) {
            if (in_array($raw3DAuthResponseData['mdStatus'], [1, 2, 3, 4])) {
                $status = 'approved';
            }
        }

        $transactionSecurity = 'MPI fallback';
        if ('approved' === $status) {
            if ('1' === $raw3DAuthResponseData['mdStatus']) {
                $transactionSecurity = 'Full 3D Secure';
            } elseif (in_array($raw3DAuthResponseData['mdStatus'], [2, 3, 4])) {
                $transactionSecurity = 'Half 3D Secure';
            }
        }

        return [
            'id'                   => null,
            'trans_id'             => null,
            'auth_code'            => null,
            'host_ref_num'         => null,
            'response'             => null,
            'order_id'             => $raw3DAuthResponseData['oid'],
            'transaction_type'     => $this->type,
            'transaction'          => empty($this->type) ? null : $this->requestDataMapper->mapTxType($this->type),
            'transaction_security' => $transactionSecurity,
            'code'                 => null,
            'md_status'            => $raw3DAuthResponseData['mdStatus'],
            'status'               => $status,
            'status_detail'        => null,
            'hash'                 => $raw3DAuthResponseData['HASH'],
            'rand'                 => $raw3DAuthResponseData['rnd'],
            'hash_params'          => $raw3DAuthResponseData['HASHPARAMS'],
            'hash_params_val'      => $raw3DAuthResponseData['HASHPARAMSVAL'],
            'masked_number'        => $raw3DAuthResponseData['maskedCreditCard'],
            'month'                => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Month'],
            'year'                 => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Year'],
            'amount'               => $raw3DAuthResponseData['amount'],
            'currency'             => array_search($raw3DAuthResponseData['currency'], $this->requestDataMapper->getCurrencyMappings()),
            'tx_status'            => null,
            'eci'                  => $raw3DAuthResponseData['eci'],
            'cavv'                 => $raw3DAuthResponseData['cavv'],
            'xid'                  => $raw3DAuthResponseData['oid'],
            'error_code'           => null,
            'error_message'        => null,
            'md_error_message'     => 'approved' !== $status ? $raw3DAuthResponseData['mdErrorMsg'] : null,
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
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object) [
            'order_id'         => $rawResponseData['OrderId'],
            'group_id'         => $rawResponseData['GroupId'],
            'response'         => $rawResponseData['Response'],
            'auth_code'        => $rawResponseData['AuthCode'],
            'host_ref_num'     => $rawResponseData['HostRefNum'],
            'proc_return_code' => $rawResponseData['ProcReturnCode'],
            'trans_id'         => $rawResponseData['TransId'],
            'num_code'         => $rawResponseData['Extra']['NUMCODE'],
            'error_code'       => $rawResponseData['Extra']['ERRORCODE'],
            'error_message'    => $rawResponseData['ErrMsg'],
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
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        $result = [
            'order_id'         => $rawResponseData['OrderId'],
            'group_id'         => $rawResponseData['GroupId'],
            'response'         => $rawResponseData['Response'],
            'auth_code'        => $rawResponseData['AuthCode'],
            'host_ref_num'     => $rawResponseData['HostRefNum'],
            'proc_return_code' => $rawResponseData['ProcReturnCode'],
            'trans_id'         => $rawResponseData['TransId'],
            'error_code'       => $rawResponseData['Extra']['ERRORCODE'],
            'num_code'         => $rawResponseData['Extra']['NUMCODE'],
            'error_message'    => $rawResponseData['ErrMsg'],
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'all'              => $rawResponseData,
        ];

        return (object) $result;
    }

    /**
     * @inheritDoc
     */
    protected function mapStatusResponse($rawResponseData)
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        $result = [
            'order_id'         => $rawResponseData['OrderId'],
            'auth_code'        => null,
            'response'         => $rawResponseData['Response'],
            'proc_return_code' => $rawResponseData['ProcReturnCode'],
            'trans_id'         => $rawResponseData['TransId'],
            'error_message'    => $rawResponseData['ErrMsg'],
            'host_ref_num'     => null,
            'order_status'     => $rawResponseData['Extra']['ORDERSTATUS'],
            'process_type'     => null,
            'masked_number'    => null,
            'num_code'         => null,
            'first_amount'     => null,
            'capture_amount'   => null,
            'status'           => $status,
            'error_code'       => null,
            'status_detail'    => $this->getStatusDetail(),
            'capture'          => false,
            'all'              => $rawResponseData,
        ];
        if ('approved' === $status) {
            $result['auth_code']      = $rawResponseData['Extra']['AUTH_CODE'];
            $result['host_ref_num']   = $rawResponseData['Extra']['HOST_REF_NUM'];
            $result['process_type']   = $rawResponseData['Extra']['CHARGE_TYPE_CD'];
            $result['first_amount']   = $rawResponseData['Extra']['ORIG_TRANS_AMT'];
            $result['capture_amount'] = $rawResponseData['Extra']['CAPTURE_AMT'];
            $result['masked_number']  = $rawResponseData['Extra']['PAN'];
            $result['num_code']       = $rawResponseData['Extra']['NUMCODE'];
            $result['capture']        = $result['first_amount'] === $result['capture_amount'];
        }

        return (object) $result;
    }

    /**
     * @inheritDoc
     */
    protected function mapPaymentResponse($responseData): array
    {
        if (empty($responseData)) {
            return $this->getDefaultPaymentResponse();
        }
        $responseData = $this->emptyStringsToNull($responseData);

        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return [
            'id'               => $responseData['AuthCode'],
            'order_id'         => $responseData['OrderId'],
            'group_id'         => $responseData['GroupId'],
            'trans_id'         => $responseData['TransId'],
            'response'         => $responseData['Response'],
            'transaction_type' => $this->type,
            'transaction'      => empty($this->type) ? null : $this->requestDataMapper->mapTxType($this->type),
            'auth_code'        => $responseData['AuthCode'],
            'host_ref_num'     => $responseData['HostRefNum'],
            'proc_return_code' => $responseData['ProcReturnCode'],
            'code'             => $responseData['ProcReturnCode'],
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => $responseData['Extra']['ERRORCODE'],
            'error_message'    => $responseData['ErrMsg'],
            'campaign_url'     => null,
            'extra'            => $responseData['Extra'],
            'all'              => $responseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapHistoryResponse($rawResponseData)
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object) [
            'order_id'         => $rawResponseData['OrderId'],
            'response'         => $rawResponseData['Response'],
            'proc_return_code' => $rawResponseData['ProcReturnCode'],
            'error_message'    => $rawResponseData['ErrMsg'],
            'num_code'         => $rawResponseData['Extra']['NUMCODE'],
            'trans_count'      => $rawResponseData['Extra']['TRXCOUNT'],
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
        if (isset($order['recurringFrequency'])) {
            $order['recurringFrequencyType'] = $this->mapRecurringFrequency($order['recurringFrequencyType']);
        }

        // Order
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
        return (object) [
            'id'       => $order['id'],
            'currency' => $order['currency'] ?? 'TRY',
            'amount'   => $order['amount'],
        ];
    }
}

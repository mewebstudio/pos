<?php

namespace Mews\Pos\Gateways;

use GuzzleHttp\Client;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\CreditCardEstPos;
use Mews\Pos\Entity\Card\CreditCardInterPos;
use Mews\Pos\Exceptions\NotImplementedException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Deniz bankin desteklidigi Gateway
 * Class InterPos
 */
class InterPos extends AbstractGateway
{
    const LANG_TR = 'tr';
    const LANG_EN = 'en';

    /**
     * @const string
     */
    public const NAME = 'InterPos';

    /**
     * Response Codes
     * @var array
     */
    protected $codes = [
        '00'  => 'approved',
        '81'  => 'bank_call',
        'E31' => 'invalid_transaction',
        'E39' => 'invalid_transaction',
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
        self::TX_REFUND   => 'Refund',
        self::TX_STATUS   => 'StatusHistory',
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
        'RUB' => 810,
    ];

    /**
     * @var InterPosAccount
     */
    protected $account;

    /**
     * @var CreditCardInterPos|null
     */
    protected $card;

    /**
     * InterPosAccount constructor.
     *
     * @param array           $config
     * @param InterPosAccount $account
     * @param array           $currencies
     */
    public function __construct($config, $account, array $currencies = [])
    {
        parent::__construct($config, $account, $currencies);
    }

    /**
     * @return InterPosAccount
     */
    public function getAccount(): InterPosAccount
    {
        return $this->account;
    }

    /**
     * @return CreditCardInterPos|null
     */
    public function getCard(): ?CreditCardInterPos
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
     * Create 3D Hash
     *
     * @param InterPosAccount $account
     * @param                 $order
     *
     * @return string
     */
    public function create3DHash(InterPosAccount $account, $order): string
    {
        $hashStr = $account->getClientId()
            .$order->id
            .$order->amount
            .$order->success_url
            .$order->fail_url
            .$this->type
            .$order->installment
            .$order->rand
            .$account->getStoreKey();

        return base64_encode(sha1($hashStr, true));
    }

    /**
     * @inheritDoc
     */
    public function send($contents, ?string $url = null)
    {
        $client = new Client();
        $url = $url ?: $this->getApiURL();

        $isXML = is_string($contents);
        $body = $isXML ? ['body' => $contents] : ['form_params' => $contents];

        $response = $client->request('POST', $url, $body);
        //genelde ;; delimiter kullanilmis, ama bazen arasinda ;;; boyle delimiter de var.
        $resultValues = preg_split('/(;;;|;;)/', $response->getBody()->getContents());
        $result       = [];
        foreach ($resultValues as $val) {
            [$key, $value] = explode('=', $val);
            $result[$key] = $value;
        }

        $this->data = $result;

        return $this;
    }

    /**
     * Check 3D Hash
     *
     * @param AbstractPosAccount $account
     * @param array              $data
     *
     * @return bool
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        $hashParams              = $data['HASHPARAMS'];
        $actualHashParamsVal     = $data['HASHPARAMSVAL'];
        $actualHash              = $data['HASH'];
        $calculatedHashParamsVal = '';

        $hashParamsArr = explode(':', $hashParams);
        foreach ($hashParamsArr as $value) {
            if (!empty($value) && isset($data[$value])) {
                $calculatedHashParamsVal .= $data[$value];
            }
        }

        $calculatedHash = $calculatedHashParamsVal.$account->getStoreKey();
        $hash           = base64_encode(sha1($calculatedHash, true));

        return $hashParams && !($calculatedHashParamsVal !== $actualHashParamsVal || $actualHash !== $hash);
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment()
    {
        $request         = Request::createFromGlobals()->request;
        $gatewayResponse = $this->emptyStringsToNull($request->all());

        $procReturnCode  = $this->getProcReturnCode($gatewayResponse);
        if ($this->check3DHash($this->account, $gatewayResponse)) {
            if ('00' !== $procReturnCode) {
                /**
                 * TODO hata durumu ele alinmasi gerekiyor
                 */
            }
            $contents = $this->create3DPaymentXML($gatewayResponse);
            $this->send($contents);
        }


        $authorizationResponse = $this->emptyStringsToNull($this->data);
        $this->response        = $this->map3DPaymentData($gatewayResponse, $authorizationResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment()
    {
        $request         = Request::createFromGlobals();
        $gatewayResponse = $this->emptyStringsToNull($request->request->all());
        $this->response  = $this->map3DPayResponseData($gatewayResponse);

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
    public function get3DFormData(): array
    {
        $secureType = '3DHost';
        $gatewayUrl = $this->get3DHostGatewayURL();
        if (self::MODEL_3D_SECURE === $this->account->getModel()) {
            $secureType = '3DModel';
            $gatewayUrl = $this->get3DGatewayURL();
        } elseif (self::MODEL_3D_PAY === $this->account->getModel()) {
            $secureType = '3DPay';
            $gatewayUrl = $this->get3DGatewayURL();
        }

        return $this->getCommon3DFormData($this->account, $this->order, $this->getLang(), $this->type, $secureType, $gatewayUrl, $this->card);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML()
    {
        $requestData = [
            'UserCode'         => $this->account->getUsername(),
            'UserPass'         => $this->account->getPassword(),
            'ShopCode'         => $this->account->getClientId(),
            'TxnType'          => $this->type,
            'SecureType'       => 'NonSecure',
            'OrderId'          => $this->order->id,
            'PurchAmount'      => $this->order->amount,
            'Currency'         => $this->order->currency,
            'InstallmentCount' => $this->order->installment,
            'MOTO'             => '0',
            'Lang'             => $this->getLang(),
        ];

        if ($this->card) {
            $requestData['CardType'] = $this->card->getCardCode();
            $requestData['Pan']      = $this->card->getNumber();
            $requestData['Expiry']   = $this->card->getExpirationDate();
            $requestData['Cvv2']     = $this->card->getCvv();
        }

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML()
    {
        $requestData = [
            'UserCode'    => $this->account->getUsername(),
            'UserPass'    => $this->account->getPassword(),
            'ShopCode'    => $this->account->getClientId(),
            'TxnType'     => $this->type,
            'SecureType'  => 'NonSecure',
            'OrderId'     => null,
            'orgOrderId'  => $this->order->id,
            'PurchAmount' => $this->order->amount,
            'Currency'    => $this->order->currency,
            'MOTO'        => '0',
        ];

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        $requestData = [
            'UserCode'                => $this->account->getUsername(),
            'UserPass'                => $this->account->getPassword(),
            'ClientId'                => $this->account->getClientId(),
            'TxnType'                 => $this->type,
            'SecureType'              => 'NonSecure',
            'OrderId'                 => $this->order->id,
            'PurchAmount'             => $this->order->amount,
            'Currency'                => $this->order->currency,
            'InstallmentCount'        => $this->order->installment,
            'MD'                      => $responseData['MD'],
            'PayerTxnId'              => $responseData['PayerTxnId'],
            'Eci'                     => $responseData['Eci'],
            'PayerAuthenticationCode' => $responseData['PayerAuthenticationCode'],
            'MOTO'                    => '0',
            'Lang'                    => $this->getLang(),
        ];

        return $requestData;
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
            'UserCode'   => $this->account->getUsername(),
            'UserPass'   => $this->account->getPassword(),
            'ShopCode'   => $this->account->getClientId(),
            'OrderId'    => null, //todo buraya hangi deger verilecek?
            'orgOrderId' => $this->order->id,
            'TxnType'    => $this->types[self::TX_STATUS],
            'SecureType' => 'NonSecure',
            'Lang'       => $this->getLang(),
        ];

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {
        $requestData = [
            'UserCode'   => $this->account->getUsername(),
            'UserPass'   => $this->account->getPassword(),
            'ShopCode'   => $this->account->getClientId(),
            'OrderId'    => null, //todo buraya hangi deger verilecek?
            'orgOrderId' => $this->order->id,
            'TxnType'    => $this->types[self::TX_CANCEL],
            'SecureType' => 'NonSecure',
            'Lang'       => $this->getLang(),
        ];

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {
        $requestData = [
            'UserCode'    => $this->account->getUsername(),
            'UserPass'    => $this->account->getPassword(),
            'ShopCode'    => $this->account->getClientId(),
            'OrderId'     => null,
            'orgOrderId'  => $this->order->id,
            'PurchAmount' => $this->order->amount,
            'TxnType'     => $this->types[self::TX_REFUND],
            'SecureType'  => 'NonSecure',
            'Lang'        => $this->getLang(),
            'MOTO'        => '0',
        ];

        return $requestData;
    }

    /**
     * Get ProcReturnCode
     *
     * @param $response
     *
     * @return string|null
     */
    protected function getProcReturnCode($response): ?string
    {
        return $response['ProcReturnCode'] ?? null;
    }

    /**
     * Get Status Detail Text
     *
     * @param string|null $procReturnCode
     *
     * @return string|null
     */
    protected function getStatusDetail(?string $procReturnCode): ?string
    {
        return $procReturnCode ? ($this->codes[$procReturnCode] ?? null) : null;
    }

    /**
     * todo test with success
     * @inheritDoc
     */
    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $status              = $raw3DAuthResponseData['mdStatus'];
        $transactionSecurity = 'MPI fallback';
        $procReturnCode      = $this->getProcReturnCode($raw3DAuthResponseData);
        if ($this->getProcReturnCode($rawPaymentResponseData) === '00') {
            if ('1' === $status) {
                $transactionSecurity = 'Full 3D Secure';
            } elseif (in_array($status, ['2', '3', '4'])) {
                $transactionSecurity = 'Half 3D Secure';
            }
        }

        $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);

        $threeDResponse = [
            'order_id'             => $paymentResponseData['order_id'] ?? $raw3DAuthResponseData['OrderId'],
            'proc_return_code'     => $paymentResponseData['proc_return_code'] ?? $procReturnCode,
            'code'                 => $paymentResponseData['proc_return_code'] ?? $procReturnCode,
            'host_ref_num'         => $paymentResponseData['host_ref_num'] ?? $raw3DAuthResponseData['HostRefNum'],
            'transaction_security' => $transactionSecurity,
            'md_status'            => $status,
            'hash'                 => $raw3DAuthResponseData['HASH'],
            'rand'                 => null,
            'hash_params'          => $raw3DAuthResponseData['HASHPARAMS'],
            'hash_params_val'      => $raw3DAuthResponseData['HASHPARAMSVAL'],
            'masked_number'        => $raw3DAuthResponseData['Pan'],
            'month'                => null,
            'year'                 => null,
            'amount'               => $raw3DAuthResponseData['PurchAmount'],
            'currency'             => $raw3DAuthResponseData['Currency'],
            'eci'                  => $raw3DAuthResponseData['Eci'],
            'tx_status'            => $raw3DAuthResponseData['TxnStat'],
            'cavv'                 => null,
            'xid'                  => $raw3DAuthResponseData['OrderId'],
            'md_error_message'     => $raw3DAuthResponseData['ErrorMessage'],
            'error_code'           => $paymentResponseData['error_code'] ?? $raw3DAuthResponseData['ErrorCode'],
            'error_message'        => $paymentResponseData['error_message'] ?? $raw3DAuthResponseData['ErrorMessage'],
            'status_detail'        => $paymentResponseData['status_detail'] ?? $this->getStatusDetail($procReturnCode),
            '3d_all'               => $raw3DAuthResponseData,
        ];

        return (object) array_merge($paymentResponseData, $threeDResponse);
    }

    /**
     * @inheritDoc
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        return $this->map3DPaymentData($raw3DAuthResponseData, $raw3DAuthResponseData);
    }

    /**
     * @inheritDoc
     */
    protected function mapRefundResponse($rawResponseData)
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = 'declined';
        if ('00' === $procReturnCode) {
            $status = 'approved';
        }

        return (object) [
            'order_id'         => $rawResponseData['OrderId'],
            'group_id'         => null,
            'response'         => null,
            'auth_code'        => null,
            'host_ref_num'     => $rawResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'code'             => $procReturnCode,
            'trans_id'         => $rawResponseData['TransId'],
            'error_code'       => $rawResponseData['ErrorCode'],
            'error_message'    => $rawResponseData['ErrorMessage'],
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapCancelResponse($rawResponseData)
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = 'declined';
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        if ('00' === $procReturnCode) {
            $status = 'approved';
        }

        return (object) [
            'order_id'         => $rawResponseData['OrderId'],
            'group_id'         => null,
            'response'         => null,
            'auth_code'        => $rawResponseData['AuthCode'],
            'host_ref_num'     => $rawResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'code'             => $procReturnCode,
            'trans_id'         => $rawResponseData['TransId'],
            'error_code'       => $rawResponseData['ErrorCode'],
            'error_message'    => $rawResponseData['ErrorMessage'],
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapStatusResponse($rawResponseData)
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = 'declined';
        if ('00' === $procReturnCode) {
            $status = 'approved';
        }

        $rawResponseData = $this->emptyStringsToNull($rawResponseData);

        return (object) [
            'order_id'         => $rawResponseData['OrderId'],
            'response'         => null,
            'proc_return_code' => $procReturnCode,
            'code'             => $procReturnCode,
            'trans_id'         => $rawResponseData['TransId'],
            'error_message'    => $rawResponseData['ErrorMessage'],
            'host_ref_num'     => null,
            'order_status'     => null, //todo success cevap alindiginda eklenecek
            'refund_amount'    => $rawResponseData['RefundedAmount'],
            'capture_amount'   => null, //todo success cevap alindiginda eklenecek
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'capture'          => null, //todo success cevap alindiginda eklenecek
            'all'              => $rawResponseData,
        ];
    }

    /**
     * todo test for success
     * @inheritDoc
     */
    protected function mapPaymentResponse($responseData)
    {
        $responseData   = $this->emptyStringsToNull($responseData);
        $status         = 'declined';
        $procReturnCode = $this->getProcReturnCode($responseData);
        if ('00' === $procReturnCode) {
            $status = 'approved';
        }

        $result = [
            'id'               => null,
            'order_id'         => null,
            'group_id'         => null,
            'trans_id'         => null,
            'response'         => null,
            'transaction_type' => $this->type,
            'transaction'      => $this->type,
            'auth_code'        => null,
            'host_ref_num'     => null,
            'proc_return_code' => $procReturnCode,
            'code'             => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => null,
            'error_message'    => null,
            'campaign_url'     => null,
            'extra'            => null,
            'all'              => $responseData,
        ];
        if (!$responseData) {
            return $result;
        }
        $result['id']            = $responseData['AuthCode'];
        $result['order_id']      = $responseData['OrderId'];
        $result['trans_id']      = $responseData['TransId'];
        $result['auth_code']     = $responseData['AuthCode'];
        $result['host_ref_num']  = $responseData['HostRefNum'];
        $result['error_code']    = $responseData['ErrorCode'];
        $result['error_message'] = $responseData['ErrorMessage'];

        return $result;
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
        $installment = '';
        if (isset($order['installment']) && $order['installment'] > 1) {
            $installment = (int) $order['installment'];
        }

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
            'id'       => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'],
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

    /**
     * bankadan gelen response'da bos string degerler var.
     * bu metod ile bos string'leri null deger olarak degistiriyoruz
     *
     * @param string|object|array $data
     *
     * @return string|object|array
     */
    private function emptyStringsToNull($data)
    {
        if (is_string($data)) {
            $data = '' === $data ? null : $data;
        } elseif (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = '' === $value ? null : $value;
            }
        }

        return $data;
    }

    /**
     * @param AbstractPosAccount      $account
     * @param                         $order
     * @param string                  $lang
     * @param string                  $txType
     * @param string                  $secureType
     * @param string                  $gatewayURL
     * @param CreditCardInterPos|null $card
     *
     * @return array
     */
    private function getCommon3DFormData(AbstractPosAccount $account, $order, string $lang, string $txType, string $secureType, string $gatewayURL, ?CreditCardInterPos $card = null): array
    {
        if (!$order) {
            return [];
        }
        $hash = $this->create3DHash($this->account, $this->order);

        $inputs = [
            'ShopCode'         => $account->getClientId(),
            'TxnType'          => $txType,
            'SecureType'       => $secureType,
            'Hash'             => $hash,
            'PurchAmount'      => $order->amount,
            'OrderId'          => $order->id,
            'OkUrl'            => $order->success_url,
            'FailUrl'          => $order->fail_url,
            'Rnd'              => $order->rand,
            'Lang'             => $lang,
            'Currency'         => $order->currency,
            'InstallmentCount' => $order->installment,
        ];

        if ($card) {
            $inputs['CardType'] = $card->getCardCode();
            $inputs['Pan']      = $card->getNumber();
            $inputs['Expiry']   = $card->getExpirationDate();
            $inputs['Cvv2']     = $card->getCvv();
        }

        return [
            'gateway' => $gatewayURL,
            'inputs'  => $inputs,
        ];
    }
}

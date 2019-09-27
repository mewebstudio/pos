<?php

namespace Mews\Pos;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class GarantiPos
 * @package Mews\Pos
 */
class GarantiPos implements PosInterface
{
    use PosHelpersTrait;

    /**
     * @const string
     */
    public const NAME = 'GarantiPos';

    /**
     * API URL
     *
     * @var string
     */
    public $url;

    /**
     * 3D Pay Gateway URL
     *
     * @var string
     */
    public $gateway;

    /**
     * Response Codes
     *
     * @var array
     */
    public $codes = [
        '00'    => 'approved',
        '01'    => 'bank_call',
        '02'    => 'bank_call',
        '05'    => 'reject',
        '09'    => 'try_again',
        '12'    => 'invalid_transaction',
        '28'    => 'reject',
        '51'    => 'insufficient_balance',
        '54'    => 'expired_card',
        '57'    => 'does_not_allow_card_holder',
        '62'    => 'restricted_card',
        '77'    => 'request_rejected',
        '99'    => 'general_error',
    ];

    /**
     * Transaction Types
     *
     * @var array
     */
    public $types = [
        'pay'   => 'sales',
        'pre'   => 'preauth',
        'post'  => 'postauth',
    ];

    /**
     * Currencies
     *
     * @var array
     */
    public $currencies = [];

    /**
     * Transaction Type
     *
     * @var string
     */
    public $type;

    /**
     * API Account
     *
     * @var array
     */
    protected $account = [];

    /**
     * Order Details
     *
     * @var array
     */
    protected $order = [];

    /**
     * Credit Card
     *
     * @var object
     */
    protected $card;

    /**
     * Request
     *
     * @var Request
     */
    protected $request;

    /**
     * Response Raw Data
     *
     * @var object
     */
    protected $data;

    /**
     * Processed Response Data
     *
     * @var mixed
     */
    public $response;

    /**
     * Configuration
     *
     * @var array
     */
    protected $config = [];

    /**
     * Mode
     *
     * @var string
     */
    protected $mode = 'PROD';

    /**
     * API version
     * @var string
     */
    protected $version = 'v0.01';

    /**
     * GarantiPost constructor.
     *
     * @param array $config
     * @param array $account
     * @param array $currencies
     */
    public function __construct($config, $account, array $currencies)
    {
        $request = Request::createFromGlobals();
        $this->request = $request->request;

        $this->config = $config;
        $this->account = $account;
        $this->currencies = $currencies;

        $this->url = isset($this->config['urls'][$this->account->env]) ?
            $this->config['urls'][$this->account->env] :
            $this->config['urls']['production'];

        $this->gateway = isset($this->config['urls']['gateway'][$this->account->env]) ?
            $this->config['urls']['gateway'][$this->account->env] :
            $this->config['urls']['gateway']['production'];

        if ($this->account->env == 'test') {
            $this->mode = 'TEST';
        }

        return $this;
    }

    /**
     * Make Security Data
     *
     * @param bool $refund
     * @return string
     */
    protected function makeSecurityData($refund = false)
    {
        $map = [
            $this->account->{($refund ? 'refund_' : null) . 'password'},
            str_pad((int) $this->account->terminal_id, 9, 0, STR_PAD_LEFT),
        ];

        return strtoupper(sha1(implode('', $map)));
    }

    /**
     * Make Hash Data
     *
     * @param $security_data
     * @return string
     */
    protected function makeHashData($security_data)
    {
        $map = [
            $this->order->id,
            $this->account->terminal_id,
            isset($this->card->number) ? $this->card->number : null,
            $this->amountFormat($this->order->amount),
            $security_data,
        ];

        return strtoupper(sha1(implode('', $map)));
    }

    /**
     * Make 3d Hash Data
     *
     * @param $security_data
     * @return string
     */
    protected function make3dHashData($security_data)
    {
        $map = [
            $this->account->terminal_id,
            $this->order->id,
            $this->amountFormat($this->order->amount),
            $this->order->success_url,
            $this->order->fail_url,
            $this->type,
            $this->order->installment ? $this->order->installment : '',
            $this->account->store_key,
            $security_data,
        ];

        return strtoupper(sha1(implode('', $map)));
    }

    /**
     * Make 3d Hash Data
     *
     * @param $security_data
     * @return string
     */
    protected function make3dRequestHashData($security_data)
    {
        $map = [
            $this->order->id,
            $this->account->terminal_id,
            $this->amountFormat($this->order->amount),
            $security_data,
        ];

        return strtoupper(sha1(implode('', $map)));
    }

    /**
     * Amount Formatter
     *
     * @param double $amount
     * @return int
     */
    protected function amountFormat($amount)
    {
        return (int) str_replace('.', '', number_format($amount, 2, '.', ''));
    }

    /**
     * Create Regular Payment XML
     *
     * @return string
     */
    protected function createRegularPaymentXML()
    {
        $security_data = $this->makeSecurityData();
        $hash_data = $this->makeHashData($security_data);

        $nodes = [
            'GVPSRequest'   => [
                'Mode'              => $this->mode,
                'Version'           => 'v0.01',
                'Terminal'          => [
                    'ProvUserID'    => $this->account->username,
                    'UserID'        => $this->account->username,
                    'HashData'      => $hash_data,
                    'ID'            => $this->account->terminal_id,
                    'MerchantID'    => $this->account->client_id,
                ],
                'Customer'          => [
                    'IPAddress'     => $this->order->ip,
                    'EmailAddress'  => $this->order->email,
                ],
                'Card'              => [
                    'Number'        => $this->card->number,
                    'ExpireDate'    => $this->card->month . $this->card->year,
                    'CVV2'          => $this->card->cvv,
                ],
                'Order'             => [
                    'OrderID'       => $this->order->id,
                    'GroupID'       => '',
                    'AddressList'   => [
                        'Address'   => [
                            'Type'          => 'S',
                            'Name'          => $this->order->name,
                            'LastName'      => '',
                            'Company'       => '',
                            'Text'          => '',
                            'District'      => '',
                            'City'          => '',
                            'PostalCode'    => '',
                            'Country'       => '',
                            'PhoneNumber'   => '',
                        ],
                    ],
                ],
                'Transaction'       => [
                    'Type'                  => $this->type,
                    'InstallmentCnt'        => $this->order->installment > 1 ? $this->order->installment : '',
                    'Amount'                => $this->amountFormat($this->order->amount),
                    'CurrencyCode'          => $this->order->currency,
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                    'Description'           => '',
                    'OriginalRetrefNum'     => '',
                ],
            ]
        ];

        return $this->createXML($nodes);
    }

    /**
     * Create Regular Payment Post XML
     *
     * @return string
     */
    protected function createRegularPostXML()
    {
        $security_data = $this->makeSecurityData();
        $hash_data = $this->makeHashData($security_data);

        $nodes = [
            'GVPSRequest'   => [
                'Mode'      => $this->mode,
                'Version'   => 'v0.1',
                'Terminal'  => [
                    'ProvUserID'    => $this->account->username,
                    'UserID'        => $this->account->username,
                    'HashData'      => $hash_data,
                    'ID'            => $this->account->terminal_id,
                    'MerchantID'    => $this->account->client_id,
                ],
                'Customer'          => [
                    'IPAddress'     => $this->order->ip,
                    'EmailAddress'  => isset($this->order->email) ? $this->order->email : null,
                ],
                'Order' => [
                    'OrderID'   => $this->order->id,
                ],
                'Transaction'   => [
                    'Type'              => $this->types[$this->order->transaction],
                    'Amount'            => $this->amountFormat($this->order->amount),
                    'CurrencyCode'      => $this->order->currency,
                    'OriginalRetrefNum' => $this->order->ref_ret_num,
                ],
            ]
        ];

        return $this->createXML($nodes);
    }

    /**
     * Create 3D Payment XML
     * @return string
     */
    protected function create3DPaymentXML()
    {
        $security_data = $this->makeSecurityData();
        $hash_data = $this->makeHashData($security_data);

        $nodes = [
            'GVPSRequest'   => [
                'Mode'              => $this->mode,
                'Version'           => $this->version,
                'ChannelCode'       => '',
                'Terminal'          => [
                    'ProvUserID'    => $this->account->username,
                    'UserID'        => $this->account->username,
                    'HashData'      => $hash_data,
                    'ID'            => $this->account->terminal_id,
                    'MerchantID'    => $this->account->client_id,
                ],
                'Customer'          => [
                    'IPAddress'     => $this->request->get('customeripaddress'),
                    'EmailAddress'  => $this->request->get('customeremailaddress'),
                ],
                'Card'              => [
                    'Number'        => '',
                    'ExpireDate'    => '',
                    'CVV2'          => '',
                ],
                'Order'             => [
                    'OrderID'       => $this->request->get('orderid'),
                    'GroupID'       => '',
                    'AddressList'   => [
                        'Address'   => [
                            'Type'          => 'B',
                            'Name'          => $this->order->name,
                            'LastName'      => '',
                            'Company'       => '',
                            'Text'          => '',
                            'District'      => '',
                            'City'          => '',
                            'PostalCode'    => '',
                            'Country'       => '',
                            'PhoneNumber'   => '',
                        ],
                    ],
                ],
                'Transaction'       => [
                    'Type'                  => $this->request->get('txntype'),
                    'InstallmentCnt'        => $this->order->installment ? $this->order->installment : '',
                    'Amount'                => $this->request->get('txnamount'),
                    'CurrencyCode'          => $this->request->get('txncurrencycode'),
                    'CardholderPresentCode' => '13',
                    'MotoInd'               => 'N',
                    'Secure3D'              => [
                        'AuthenticationCode'    => $this->request->get('cavv'),
                        'SecurityLevel'         => $this->request->get('eci'),
                        'TxnID'                 => $this->request->get('xid'),
                        'Md'                    => $this->request->get('md'),
                    ],
                ],
            ]
        ];

        return $this->createXML($nodes);
    }

    /**
     * Get ProcReturnCode
     *
     * @return string|null
     */
    protected function getProcReturnCode()
    {
        return isset($this->data->Transaction->Response->Code) ? (string) $this->data->Transaction->Response->Code : null;
    }

    /**
     * Get Status Detail Text
     *
     * @return string|null
     */
    protected function getStatusDetail()
    {
        $proc_return_code =  $this->getProcReturnCode();

        return $proc_return_code ? (isset($this->codes[$proc_return_code]) ? (string) $this->codes[$proc_return_code] : null) : null;
    }

    /**
     * Create 3D Hash
     *
     * @return string
     */
    public function create3DHash()
    {
        $hash_str = '';

        if ($this->account->model == '3d') {
            $hash_str = $this->account->client_id . $this->order->id . $this->order->amount . $this->order->success_url . $this->order->fail_url . $this->order->rand . $this->account->store_key;
        } elseif ($this->account->model == '3d_pay') {
            $hash_str = $this->account->client_id . $this->order->id . $this->order->amount . $this->order->success_url . $this->order->fail_url . $this->order->transaction_type . $this->order->installment . $this->order->rand . $this->account->store_key;
        }

        return base64_encode(pack('H*', sha1($hash_str)));
    }

    /**
     * Regular Payment
     *
     * @return $this
     * @throws GuzzleException
     */
    public function makeRegularPayment()
    {
        $contents = '';
        if (in_array($this->order->transaction, ['pay', 'pre'])) {
            $contents = $this->createRegularPaymentXML();
        } elseif ($this->order->transaction == 'post') {
            $contents = $this->createRegularPostXML();
        }

        $this->send($contents);

        $status = 'declined';
        if ($this->getProcReturnCode() == '00') {
            $status = 'approved';
        }

        $this->response = (object) [
            'id'                => isset($this->data->Transaction->AuthCode) ? $this->printData($this->data->Transaction->AuthCode) : null,
            'order_id'          => isset($this->data->Order->OrderID) ? $this->printData($this->data->Order->OrderID) : null,
            'group_id'          => isset($this->data->Order->GroupID) ? $this->printData($this->data->Order->GroupID) : null,
            'trans_id'          => isset($this->data->Transaction->AuthCode) ? $this->printData($this->data->Transaction->AuthCode) : null,
            'response'          => isset($this->data->Transaction->Response->Message) ? $this->printData($this->data->Transaction->Response->Message) : null,
            'transaction_type'  => $this->type,
            'transaction'       => $this->order->transaction,
            'auth_code'         => isset($this->data->Transaction->AuthCode) ? $this->printData($this->data->Transaction->AuthCode) : null,
            'host_ref_num'      => isset($this->data->Transaction->RetrefNum) ? $this->printData($this->data->Transaction->RetrefNum) : null,
            'ret_ref_num'       => isset($this->data->Transaction->RetrefNum) ? $this->printData($this->data->Transaction->RetrefNum) : null,
            'hash_data'         => isset($this->data->Transaction->HashData) ? $this->printData($this->data->Transaction->HashData) : null,
            'proc_return_code'  => $this->getProcReturnCode(),
            'code'              => $this->getProcReturnCode(),
            'status'            => $status,
            'status_detail'     => $this->getStatusDetail(),
            'error_code'        => isset($this->data->Transaction->Response->Code) ? $this->printData($this->data->Transaction->Response->Code) : null,
            'error_message'     => isset($this->data->Transaction->Response->ErrorMsg) ? $this->printData($this->data->Transaction->Response->ErrorMsg) : null,
            'campaign_url'      => isset($this->data->Transaction->CampaignChooseLink) ? $this->printData($this->data->Transaction->CampaignChooseLink) : null,
            'extra'             => isset($this->data->Extra) ? $this->data->Extra : null,
            'all'               => $this->data,
            'original'          => $this->data,
        ];

        return $this;
    }

    /**
     * Make 3D Payment
     *
     * @return $this
     * @throws GuzzleException
     */
    public function make3DPayment()
    {
        $status = 'declined';
        $response = 'Declined';
        $proc_return_code = '99';
        $transaction_security = 'MPI fallback';
        if (in_array($this->request->get('mdstatus'), [1, 2, 3, 4])) {
            if ($this->request->get('mdstatus') == '1') {
                $transaction_security = 'Full 3D Secure';
            } elseif (in_array($this->request->get('mdstatus'), [2, 3, 4])) {
                $transaction_security = 'Half 3D Secure';
            }

            $contents = $this->create3DPaymentXML();
            $this->send($contents);

            if ($this->data->Transaction->Response->ReasonCode == '00') {
                $response = 'Approved';
                $proc_return_code = $this->data->Transaction->Response->ReasonCode;
                $status = 'approved';
            }
        }

        $this->response = (object) [
            'id'                    => isset($this->data->Transaction->AuthCode) ? $this->printData($this->data->Transaction->AuthCode) : null,
            'order_id'              => $this->request->get('oid'),
            'group_id'              => isset($this->data->Transaction->SequenceNum) ? $this->printData($this->data->Transaction->SequenceNum) : null,
            'trans_id'              => $this->request->get('transid'),
            'response'              => $response,
            'transaction_type'      => $this->type,
            'transaction'           => $this->order->transaction,
            'transaction_security'  => $transaction_security,
            'auth_code'             => isset($this->data->Transaction->AuthCode) ? $this->printData($this->data->Transaction->AuthCode) : null,
            'host_ref_num'          => isset($this->data->Transaction->RetrefNum) ? $this->printData($this->data->Transaction->RetrefNum) : null,
            'proc_return_code'      => $proc_return_code,
            'ret_ref_num'           => isset($this->data->Transaction->RetrefNum) ? $this->printData($this->data->Transaction->RetrefNum) : null,
            'batch_num'             => isset($this->data->Transaction->BatchNum) ? $this->printData($this->data->Transaction->BatchNum) : null,
            'code'                  => $proc_return_code,
            'status'                => $status,
            'status_detail'         => $this->getStatusDetail(),
            'error_code'            => isset($this->data->Transaction->Response->ErrorCode) ? $this->printData($this->data->Transaction->Response->ErrorCode) : null,
            'error_message'         => isset($this->data->Transaction->Response->ErrorMsg) ? $this->printData($this->data->Transaction->Response->ErrorMsg) : null,
            'reason_code'           => isset($this->data->Transaction->Response->ReasonCode) ? $this->printData($this->data->Transaction->Response->ReasonCode) : null,
            'campaign_url'          => isset($this->data->Transaction->CampaignChooseLink) ? $this->printData($this->data->Transaction->CampaignChooseLink) : null,
            'md_status'             => $this->request->get('mdstatus'),
            'rand'                  => (string) $this->request->get('rnd'),
            'hash'                  => (string) $this->request->get('secure3dhash'),
            'hash_params'           => (string) $this->request->get('hashparams'),
            'hash_params_val'       => (string) $this->request->get('hashparamsval'),
            'secure_3d_hash'        => (string) $this->request->get('secure3dhash'),
            'secure_3d_level'       => (string) $this->request->get('secure3dsecuritylevel'),
            'masked_number'         => (string) $this->request->get('MaskedPan'),
            'amount'                => (string) $this->request->get('amount'),
            'currency'              => (string) $this->request->get('currency'),
            'tx_status'             => (string) $this->request->get('txstatus'),
            'eci'                   => (string) $this->request->get('eci'),
            'cavv'                  => (string) $this->request->get('cavv'),
            'xid'                   => (string) $this->request->get('xid'),
            'md_error_message'      => (string) $this->request->get('mderrormessage'),
            'name'                  => (string) $this->request->get('firmaadi'),
            'email'                 => (string) $this->request->get('Email'),
            'extra'                 => null,
            'all'                   => $this->data,
            '3d_all'                => $this->request->all(),
        ];

        return $this;
    }

    /**
     * Make 3D Pay Payment
     *
     * @return $this
     */
    public function make3DPayPayment()
    {
        $status = 'declined';
        $response = 'Declined';
        $proc_return_code = $this->request->get('procreturncode');

        $transaction_security = 'MPI fallback';
        if (in_array($this->request->get('mdstatus'), [1, 2, 3, 4])) {
            if ($this->request->get('mdstatus') == '1') {
                $transaction_security = 'Full 3D Secure';
            } elseif (in_array($this->request->get('mdstatus'), [2, 3, 4])) {
                $transaction_security = 'Half 3D Secure';
            }

            $status = 'approved';
            $response = 'Approved';
        }

        $this->response = (object) [
            'id'                    => (string) $this->request->get('authcode'),
            'order_id'              => (string) $this->request->get('oid'),
            'trans_id'              => (string) $this->request->get('transid'),
            'auth_code'             => (string) $this->request->get('authcode'),
            'host_ref_num'          => (string) $this->request->get('hostrefnum'),
            'response'              => $response,
            'transaction_type'      => $this->type,
            'transaction'           => $this->order->transaction,
            'transaction_security'  => $transaction_security,
            'proc_return_code'      => $proc_return_code,
            'code'                  => $proc_return_code,
            'md_status'             => $this->request->get('mdStatus'),
            'status'                => $status,
            'status_detail'         => isset($this->codes[$this->request->get('ProcReturnCode')]) ? (string) $this->request->get('ProcReturnCode') : null,
            'hash'                  => (string) $this->request->get('secure3dhash'),
            'rand'                  => (string) $this->request->get('rnd'),
            'hash_params'           => (string) $this->request->get('hashparams'),
            'hash_params_val'       => (string) $this->request->get('hashparamsval'),
            'masked_number'         => (string) $this->request->get('MaskedPan'),
            'amount'                => (string) $this->request->get('amount'),
            'currency'              => (string) $this->request->get('currency'),
            'tx_status'             => (string) $this->request->get('txstatus'),
            'eci'                   => (string) $this->request->get('eci'),
            'cavv'                  => (string) $this->request->get('cavv'),
            'xid'                   => (string) $this->request->get('xid'),
            'error_code'            => (string) $this->request->get('errcode'),
            'error_message'         => (string) $this->request->get('errmsg'),
            'md_error_message'      => (string) $this->request->get('mderrormessage'),
            'campaign_url'          => null,
            'name'                  => (string) $this->request->get('firmaadi'),
            'email'                 => (string) $this->request->get('Email'),
            'extra'                 => $this->request->get('Extra'),
            'all'                   => $this->request->all(),
        ];

        return $this;
    }

    /**
     * Get 3d Form Data
     *
     * @return array
     */
    public function get3DFormData()
    {
        $security_data = $this->makeSecurityData();
        $hash_data = $this->make3dHashData($security_data);

        $inputs = [
            'secure3dsecuritylevel' => $this->account->model == '3d_pay' ? '3D_PAY' : '3D',
            'mode'                  => $this->mode,
            'apiversion'            => $this->version,
            'terminalprovuserid'    => $this->account->username,
            'terminaluserid'        => $this->account->username,
            'terminalmerchantid'    => $this->account->client_id,
            'txntype'               => $this->type,
            'txnamount'             => $this->amountFormat($this->order->amount),
            'txncurrencycode'       => $this->order->currency,
            'txninstallmentcount'   => $this->order->installment > 1 ? $this->order->installment : '',
            'orderid'               => $this->order->id,
            'terminalid'            => $this->account->terminal_id,
            'successurl'            => $this->order->success_url,
            'errorurl'              => $this->order->fail_url,
            'customeremailaddress'  => isset($this->order->email) ? $this->order->email : null,
            'customeripaddress'     => $this->order->ip,
            'cardnumber'            => $this->card->number,
            'cardexpiredatemonth'   => $this->card->month,
            'cardexpiredateyear'    => $this->card->year,
            'cardcvv2'              => $this->card->cvv,
            'secure3dhash'          => $hash_data,
        ];

        return [
            'gateway'       => $this->gateway,
            'success_url'   => $this->order->success_url,
            'fail_url'      => $this->order->fail_url,
            'rand'          => $this->order->rand,
            'hash'          => $hash_data,
            'inputs'        => $inputs,
        ];
    }

    /**
     * Send contents to WebService
     *
     * @param $contents
     * @return $this
     * @throws GuzzleException
     */
    public function send($contents)
    {
        $client = new Client();

        $response = $client->request('POST', $this->url, [
            'body'  => $contents
        ]);

        $xml = new SimpleXMLElement($response->getBody());

        $this->data = (object) json_decode(json_encode($xml));

        return $this;
    }

    /**
     * Prepare Order
     *
     * @param object $order
     * @param object null $card
     * @return mixed
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

        if ($this->card) {
            $this->card->month = str_pad($this->card->month, 2, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Make Payment
     *
     * @param object $card
     * @return mixed
     * @throws UnsupportedPaymentModelException
     * @throws GuzzleException
     */
    public function payment($card)
    {
        $this->card = $card;

        $model = 'regular';
        if (isset($this->account->model) && $this->account->model) {
            $model = $this->account->model;
        }

        if ($model == 'regular') {
            $this->makeRegularPayment();
        } elseif ($model == '3d') {
            $this->make3DPayment();
        } elseif ($model == '3d_pay') {
            $this->make3DPayPayment();
        } else {
            throw new UnsupportedPaymentModelException();
        }

        return $this;
    }

    /**
     * Refund or Cancel Order
     *
     * @param array $meta
     * @param $type
     * @return $this
     * @throws GuzzleException
     */
    protected function refundOrCancel(array $meta, $type)
    {
        $this->order = (object) [
            'id'        => $meta['order_id'],
            'amount'    => isset($meta['amount']) ? $meta['amount'] : null,
        ];

        $security_data = $this->makeSecurityData(true);
        $hash_data = $this->makeHashData($security_data);

        $currency = (int) $this->currencies[$meta['currency']];

        $nodes = [
            'GVPSRequest'   => [
                'Mode'          => $this->mode,
                'Version'       => $this->version,
                'ChannelCode'   => '',
                'Terminal'      => [
                    'ProvUserID'    => $this->account->refund_username,
                    'UserID'        => $this->account->refund_username,
                    'HashData'      => $hash_data,
                    'ID'            => $this->account->terminal_id,
                    'MerchantID'    => $this->account->client_id,
                ],
                'Customer'      => [
                    'IPAddress'     => isset($meta['ip']) ? $meta['ip'] : null,
                    'EmailAddress'  => isset($meta['email']) ? $meta['email'] : null,
                ],
                'Order'         => [
                    'OrderID'   => $this->order->id,
                    'GroupID'   => '',
                ],
                'Transaction'   => [
                    'Type'                  => $type,
                    'InstallmentCnt'        => '',
                    'Amount'                => $this->amountFormat($this->order->amount),
                    'CurrencyCode'          => $currency,
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                    'OriginalRetrefNum'     => $meta['ref_ret_num'],
                ],
            ]
        ];

        $xml = $this->createXML($nodes);
        $this->send($xml);

        $status = 'declined';
        if ($this->getProcReturnCode() == '00') {
            $status = 'approved';
        }

        $this->response = (object) [
            'id'                => isset($this->data->Transaction->AuthCode) ? $this->printData($this->data->Transaction->AuthCode) : null,
            'order_id'          => isset($this->data->Order->OrderID) ? $this->printData($this->data->Order->OrderID) : null,
            'group_id'          => isset($this->data->Order->GroupID) ? $this->printData($this->data->Order->GroupID) : null,
            'trans_id'          => isset($this->data->Transaction->AuthCode) ? $this->printData($this->data->Transaction->AuthCode) : null,
            'response'          => isset($this->data->Transaction->Response->Message) ? $this->printData($this->data->Transaction->Response->Message) : null,
            'auth_code'         => isset($this->data->Transaction->AuthCode) ? $this->data->Transaction->AuthCode : null,
            'host_ref_num'      => isset($this->data->Transaction->RetrefNum) ? $this->printData($this->data->Transaction->RetrefNum) : null,
            'ret_ref_num'       => isset($this->data->Transaction->RetrefNum) ? $this->printData($this->data->Transaction->RetrefNum) : null,
            'hash_data'         => isset($this->data->Transaction->HashData) ? $this->printData($this->data->Transaction->HashData) : null,
            'proc_return_code'  => $this->getProcReturnCode(),
            'code'              => $this->getProcReturnCode(),
            'error_code'        => isset($this->data->Transaction->Response->Code) ? $this->printData($this->data->Transaction->Response->Code) : null,
            'error_message'     => isset($this->data->Transaction->Response->ErrorMsg) ? $this->printData($this->data->Transaction->Response->ErrorMsg) : null,
            'status'            => $status,
            'status_detail'     => $this->getStatusDetail(),
            'all'               => $this->data,
        ];

        return $this;
    }

    /**
     * Refund Order
     *
     * @param $meta
     * @return $this
     * @throws GuzzleException
     */
    public function refund(array $meta)
    {
        return $this->refundOrCancel($meta, 'refund');
    }

    /**
     * Cancel Order
     *
     * @param array $meta
     * @return $this
     * @throws GuzzleException
     */
    public function cancel(array $meta)
    {
        return $this->refundOrCancel($meta, 'void');
    }

    /**
     * Order Status or History
     *
     * @param array $meta
     * @param $type
     * @return $this
     * @throws GuzzleException
     */
    protected function statusOrHistory(array $meta, $type)
    {
        $obj_item = 'OrderInqResult';
        if ($type == 'orderhistoryinq') {
            $obj_item = 'OrderHistInqResult';
        }

        $this->order = (object) [
            'id'        => isset($meta['order_id']) ? $meta['order_id'] : null,
            'currency'  => isset($this->currencies[$meta['currency']]) ? $this->currencies[$meta['currency']] : null,
            'amount'    => '1',
        ];

        $security_data = $this->makeSecurityData();
        $hash_data = $this->makeHashData($security_data);

        $xml = $this->createXML([
            'GVPSRequest'   => [
                'Mode'          => $this->mode,
                'Version'       => 'v0.01',
                'ChannelCode'   => '',
                'Terminal'      => [
                    'ProvUserID'    => $this->account->username,
                    'UserID'        => $this->account->username,
                    'HashData'      => $hash_data,
                    'ID'            => $this->account->terminal_id,
                    'MerchantID'    => $this->account->client_id,
                ],
                'Customer'      => [
                    'IPAddress'     => isset($meta['ip']) ? $meta['ip'] : null,
                    'EmailAddress'  => isset($meta['email']) ? $meta['email'] : null,
                ],
                'Order'         => [
                    'OrderID'   => $this->order->id,
                    'GroupID'   => '',
                ],
                'Card'  => [
                    'Number'        => '',
                    'ExpireDate'    => '',
                    'CVV2'          => '',
                ],
                'Transaction'   => [
                    'Type'                  => $type,
                    'InstallmentCnt'        => '',
                    'Amount'                => $this->order->amount ? $this->amountFormat($this->order->amount) : null,
                    'CurrencyCode'          => $this->order->currency,
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                ],
            ]
        ]);

        $this->send($xml);

        $status = 'declined';
        if ($this->getProcReturnCode() == '00') {
            $status = 'approved';
        }

        $data = [
            'id'                => isset($this->data->Order->{$obj_item}->AuthCode) ? $this->printData($this->data->Order->{$obj_item}->AuthCode) : null,
            'order_id'          => isset($this->data->Order->OrderID) ? $this->printData($this->data->Order->OrderID) : null,
            'group_id'          => isset($this->data->Order->GroupID) ? $this->printData($this->data->Order->GroupID) : null,
            'trans_id'          => isset($this->data->Transaction->AuthCode) ? $this->printData($this->data->Transaction->AuthCode) : null,
            'response'          => isset($this->data->Transaction->Response->Message) ? $this->printData($this->data->Transaction->Response->Message) : null,
            'auth_code'         => isset($this->data->Order->{$obj_item}->AuthCode) ? $this->printData($this->data->Order->{$obj_item}->AuthCode) : null,
            'host_ref_num'      => isset($this->data->Order->{$obj_item}->RetrefNum) ? $this->printData($this->data->Order->{$obj_item}->RetrefNum) : null,
            'ret_ref_num'       => isset($this->data->Order->{$obj_item}->RetrefNum) ? $this->printData($this->data->Order->{$obj_item}->RetrefNum) : null,
            'hash_data'         => isset($this->data->Transaction->HashData) ? $this->printData($this->data->Transaction->HashData) : null,
            'proc_return_code'  => $this->getProcReturnCode(),
            'code'              => $this->getProcReturnCode(),
            'status'            => $status,
            'status_detail'     => $this->getStatusDetail(),
            'error_code'        => isset($this->data->Transaction->Response->Code) ? $this->printData($this->data->Transaction->Response->Code) : null,
            'error_message'     => isset($this->data->Transaction->Response->ErrorMsg) ? $this->printData($this->data->Transaction->Response->ErrorMsg) : null,
            'extra'             => isset($this->data->Extra) ? $this->data->Extra : null,
            'all'               => $this->data,
            'original'          => $this->data,
        ];

        if ($type == 'orderhistoryinq') {
            $data = array_merge($data, [
                'order_txn' => isset($this->data->Order->OrderHistInqResult->OrderTxnList->OrderTxn) ? $this->data->Order->OrderHistInqResult->OrderTxnList->OrderTxn : []
            ]);
        }

        $this->response = (object) $data;

        return $this;
    }

    /**
     * Order Status
     *
     * @param array $meta
     * @return $this
     * @throws GuzzleException
     */
    public function status(array $meta)
    {
        return $this->statusOrHistory($meta, 'orderinq');
    }

    /**
     * Order History
     *
     * @param array $meta
     * @return $this
     * @throws GuzzleException
     */
    public function history(array $meta)
    {
        return $this->statusOrHistory($meta, 'orderhistoryinq');
    }
}

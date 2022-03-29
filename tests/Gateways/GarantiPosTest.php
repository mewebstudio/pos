<?php

namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\CreditCardGarantiPos;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\GarantiPos;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class GarantiPosTest extends TestCase
{
    /**
     * @var GarantiPosAccount
     */
    private $account;
    private $config;

    /**
     * @var CreditCardGarantiPos
     */
    private $card;
    private $order;
    /**
     * @var XmlEncoder
     */
    private $xmlDecoder;
    /**
     * @var GarantiPos
     */
    private $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createGarantiPosAccount('garanti', '7000679', 'PROVAUT', '123qweASD/', '30691298', '3d', '12345678', 'PROVRFN', '123qweASD/');

        $this->card = new CreditCardGarantiPos('5555444433332222', '21', '12', '122');

        $this->order = [
            'id'          => 'order222',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'rand'        => microtime(),
            'ip'          => '156.155.154.153',
        ];

        $this->pos = PosFactory::createPosGateway($this->account);
        $this->pos->setTestMode(true);

        $this->xmlDecoder = new XmlEncoder();
    }

    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
    }

    public function testPrepare()
    {

        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());
    }

    public function testAmountFormat()
    {
        $this->assertEquals(100000, GarantiPos::amountFormat(1000));
        $this->assertEquals(100000, GarantiPos::amountFormat(1000.00));
        $this->assertEquals(100001, GarantiPos::amountFormat(1000.01));
    }

    public function testGet3DFormWithCardData()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $form = [
            'gateway' => $this->config['banks'][$this->account->getBank()]['urls']['gateway']['test'],
            'inputs'  => [
                'secure3dsecuritylevel' => $this->account->getModel() === '3d_pay' ? '3D_PAY' : '3D',
                'mode'                  => 'TEST',
                'apiversion'            => GarantiPos::API_VERSION,
                'terminalprovuserid'    => $this->account->getUsername(),
                'terminaluserid'        => $this->account->getUsername(),
                'terminalmerchantid'    => $this->account->getClientId(),
                'txntype'               => 'sales',
                'txnamount'             => GarantiPos::amountFormat($this->order['amount']),
                'txncurrencycode'       => $this->pos->mapCurrency($this->order['currency']),
                'txninstallmentcount'   => empty($this->order['installment']) ? '' : $this->order['installment'],
                'orderid'               => $this->order['id'],
                'terminalid'            => $this->account->getTerminalId(),
                'successurl'            => $this->order['success_url'],
                'errorurl'              => $this->order['fail_url'],
                'customeremailaddress'  => isset($this->order['email']) ? $this->order['email'] : null,
                'customeripaddress'     => $this->order['ip'],
                'secure3dhash'          => '1D319D5EA945F5730FF5BCC970FF96690993F4BD',
                'cardnumber'            => $this->card->getNumber(),
                'cardexpiredatemonth'   => $this->card->getExpireMonth(),
                'cardexpiredateyear'    => $this->card->getExpireYear(),
                'cardcvv2'              => $this->card->getCvv(),
            ],
        ];

        $actualForm = $this->pos->get3DFormData();
        $this->assertNotEmpty($actualForm['inputs']);

        $this->assertEquals($form, $actualForm);
    }


    public function testGet3DFormWithoutCardData()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY);

        $form = [
            'gateway' => $this->config['banks'][$this->account->getBank()]['urls']['gateway']['test'],
            'inputs'  => [
                'secure3dsecuritylevel' => $this->account->getModel() === '3d_pay' ? '3D_PAY' : '3D',
                'mode'                  => 'TEST',
                'apiversion'            => GarantiPos::API_VERSION,
                'terminalprovuserid'    => $this->account->getUsername(),
                'terminaluserid'        => $this->account->getUsername(),
                'terminalmerchantid'    => $this->account->getClientId(),
                'txntype'               => 'sales',
                'txnamount'             => GarantiPos::amountFormat($this->order['amount']),
                'txncurrencycode'       => $this->pos->mapCurrency($this->order['currency']),
                'txninstallmentcount'   => empty($this->order['installment']) ? '' : $this->order['installment'],
                'orderid'               => $this->order['id'],
                'terminalid'            => $this->account->getTerminalId(),
                'successurl'            => $this->order['success_url'],
                'errorurl'              => $this->order['fail_url'],
                'customeremailaddress'  => isset($this->order['email']) ? $this->order['email'] : null,
                'customeripaddress'     => $this->order['ip'],
                'secure3dhash'          => '1D319D5EA945F5730FF5BCC970FF96690993F4BD',
            ],
        ];

        $actualForm = $this->pos->get3DFormData();
        $this->assertNotEmpty($actualForm['inputs']);

        $this->assertEquals($form, $actualForm);
    }

    public function testCreateRegularPaymentXML()
    {
        $order = [
            'id'          => '2020110828BC',
            'email'       => 'samp@iexample.com',
            'name'        => 'john doe',
            'user_id'     => '1535',
            'ip'          => '192.168.1.0',
            'amount'      => 100.01,
            'installment' => 0,
            'currency'    => 'TRY',
        ];


        $card = new CreditCardGarantiPos('5555444433332222', '22', '01', '123', 'ahmet');
        /**
         * @var GarantiPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->setTestMode(true);
        $pos->prepare($order, AbstractGateway::TX_PAY, $card);

        $actualXML = $pos->createRegularPaymentXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRegularPaymentXMLData($pos->getOrder(), $pos->getCard(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
    }

    public function testCreateRegularPostXML()
    {
        $order = [
            'id'          => '2020110828BC',
            'ref_ret_num' => '831803579226',
            'currency'    => 'TRY',
            'amount'      => 100.01,
            'email'       => 'samp@iexample.com',
            'ip'          => '192.168.1.0',
        ];

        /**
         * @var GarantiPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->setTestMode(true);
        $pos->prepare($order, AbstractGateway::TX_POST_PAY);

        $actualXML = $pos->createRegularPostXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRegularPostXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
    }

    public function testCreate3DPaymentXML()
    {
        $order = [
            'id'          => '2020110828BC',
            'email'       => 'samp@iexample.com',
            'name'        => 'john doe',
            'user_id'     => '1535',
            'ip'          => '192.168.1.0',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
        ];
        $responseData = [
            'orderid'              => '2020110828BC',
            'md'                   => '1',
            'xid'                  => '100000005xid',
            'eci'                  => '100000005eci',
            'cavv'                 => 'cavv',
            'txncurrencycode'      => 'txncurrencycode',
            'txnamount'            => 'txnamount',
            'txntype'              => 'txntype',
            'customeripaddress'    => 'customeripaddress',
            'customeremailaddress' => 'customeremailaddress',
        ];

        /**
         * @var GarantiPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->setTestMode(true);
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actualXML = $pos->create3DPaymentXML($responseData);
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSample3DPaymentXMLData($pos->getOrder(), $pos->getAccount(), $responseData);
        $this->assertEquals($expectedData, $actualData);
    }

    public function testCreateStatusXML()
    {
        $order = [
            'id'       => '2020110828BC',
            'currency' => 'TRY',
        ];

        /**
         * @var GarantiPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->setTestMode(true);
        $pos->prepare($order, AbstractGateway::TX_STATUS);

        $actualXML = $pos->createStatusXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleStatusXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
    }


    public function testCreateCancelXML()
    {
        $order = [
            'id'          => '2020110828BC',
            'currency'    => 'TRY',
            'amount'      => 10.01,
            'ref_ret_num' => '831803579226',
        ];

        /**
         * @var GarantiPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->setTestMode(true);
        $pos->prepare($order, AbstractGateway::TX_CANCEL);

        $actualXML = $pos->createCancelXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleCancelXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
    }

    public function testCreateRefundXML()
    {
        $order = [
            'id'          => '2020110828BC',
            'currency'    => 'TRY',
            'amount'      => 10.01,
            'ref_ret_num' => '831803579226',
        ];

        /**
         * @var GarantiPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->setTestMode(true);
        $pos->prepare($order, AbstractGateway::TX_REFUND);

        $actualXML = $pos->createRefundXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRefundXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData['Transaction']);
    }

    /**
     * @uses \Mews\Pos\Gateways\GarantiPos::map3DPayResponseData()
     *
     * @return void
     */
    public function testMap3DPayResponseDataSuccess()
    {
        $gatewayResponse = [
            "xid" => "bVi+A/h6SjXabcde=",
            "mdstatus" => "1",
            "mderrormessage" => "TROY Gateway Result: [Code: '000', Message: 'Success', Description: 'Successful']",
            "txnstatus" => "",
            "eci" => "",
            "cavv" => "ABIBBDYABBBBBAAABAAAAAAAAAAA=",
            "paressyntaxok" => "",
            "paresverified" => "",
            "version" => "",
            "ireqcode" => "",
            "ireqdetail" => "",
            "vendorcode" => "",
            "cavvalgorithm" => "",
            "md" => "longstring",
            "terminalid" => "10012345",
            "oid" => "1221513409",
            "authcode" => "",
            "response" => "",
            "errmsg" => "",
            "hostmsg" => "",
            "procreturncode" => "",
            "transid" => "1001513409",
            "hostrefnum" => "",
            "rnd" => "vNOc4abcde2aCL/HBzs",
            "hash" => "1I9zDunx0hashTRI816trOG0Ao0=",
            "hashparams" => "clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:",
            "hashparamsval" => "longstring",
            "clientid" => "10012345",
            "MaskedPan" => "454311***7965",
            "customeripaddress" => "134.170.165.149",
            "orderid" => "1221513409",
            "txntype" => "sales",
            "terminalprovuserid" => "PROVAUT",
            "secure3dhash" => "BE3C507794AhashE021E8EA239415D774EEF2",
            "mode" => "PROD",
            "txncurrencycode" => "949",
            "customeremailaddress" => "admin@admin.com",
            "terminaluserid" => "PROVAUT",
            "terminalmerchantid" => "1234567",
            "secure3dsecuritylevel" => "3D",
            "user_id" => "1",
            "errorurl" => "https://example.com/odeme_basarisiz",
            "apiversion" => "v0.01",
            "txnamount" => "100",
            "txninstallmentcount" => "",
            "successurl" => "https://example.com/odeme_basarili",
        ];
        $expected = '{"id":"","order_id":"1221513409","trans_id":"1001513409","auth_code":"","host_ref_num":"","response":"Approved","transaction_type":null,"transaction":null,"transaction_security":"Full 3D Secure","proc_return_code":"","code":"","md_status":"1","status":"approved","status_detail":null,"hash":"BE3C507794AhashE021E8EA239415D774EEF2","rand":"vNOc4abcde2aCL\/HBzs","hash_params":"clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:","hash_params_val":"longstring","masked_number":"454311***7965","amount":"100","currency":"949","tx_status":"","eci":"","cavv":"ABIBBDYABBBBBAAABAAAAAAAAAAA=","xid":"bVi+A\/h6SjXabcde=","error_code":null,"error_message":"","md_error_message":"TROY Gateway Result: [Code: \'000\', Message: \'Success\', Description: \'Successful\']","campaign_url":null,"email":"admin@admin.com","extra":null,"all":{"xid":"bVi+A\/h6SjXabcde=","mdstatus":"1","mderrormessage":"TROY Gateway Result: [Code: \'000\', Message: \'Success\', Description: \'Successful\']","txnstatus":"","eci":"","cavv":"ABIBBDYABBBBBAAABAAAAAAAAAAA=","paressyntaxok":"","paresverified":"","version":"","ireqcode":"","ireqdetail":"","vendorcode":"","cavvalgorithm":"","md":"longstring","terminalid":"10012345","oid":"1221513409","authcode":"","response":"","errmsg":"","hostmsg":"","procreturncode":"","transid":"1001513409","hostrefnum":"","rnd":"vNOc4abcde2aCL\/HBzs","hash":"1I9zDunx0hashTRI816trOG0Ao0=","hashparams":"clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:","hashparamsval":"longstring","clientid":"10012345","MaskedPan":"454311***7965","customeripaddress":"134.170.165.149","orderid":"1221513409","txntype":"sales","terminalprovuserid":"PROVAUT","secure3dhash":"BE3C507794AhashE021E8EA239415D774EEF2","mode":"PROD","txncurrencycode":"949","customeremailaddress":"admin@admin.com","terminaluserid":"PROVAUT","terminalmerchantid":"1234567","secure3dsecuritylevel":"3D","user_id":"1","errorurl":"https:\/\/example.com\/odeme_basarisiz","apiversion":"v0.01","txnamount":"100","txninstallmentcount":"","successurl":"https:\/\/example.com\/odeme_basarili"}}';
        $method = $this->getMethod('map3DPayResponseData');
        $result1 = $method->invoke($this->pos, $gatewayResponse);

        $this->assertIsObject($result1);
        $this->assertSame($expected, json_encode($result1));
    }

    /**
     * @uses \Mews\Pos\Gateways\GarantiPos::map3DPayResponseData()
     *
     * @return void
     */
    public function testMap3DPayResponseDataFail()
    {
        $failResponse =  [
            "mdstatus" => "0",
            "mderrormessage" => "User Gave Up",
            "errmsg" => "User Gave Up",
            "clientid" => "10012345",
            "oid" => "1221166825",
            "response" => "Error",
            "procreturncode" => "99",
            "customeripaddress" => "111.222.333.444",
            "orderid" => "1221166825",
            "txntype" => "sales",
            "terminalprovuserid" => "PROVAUT",
            "secure3dhash" => "hashhash",
            "mode" => "PROD",
            "terminalid" => "10012345",
            "txncurrencycode" => "949",
            "customeremailaddress" => "admin@admin.com",
            "terminaluserid" => "PROVAUT",
            "terminalmerchantid" => "5220607",
            "secure3dsecuritylevel" => "3D",
            "user_id" => "1",
            "errorurl" => "https://example.com/odeme_basarisiz",
            "apiversion" => "v0.01",
            "txnamount" => "9000",
            "txninstallmentcount" => "",
            "successurl" => "https://example.com/odeme_basarili",
        ];
        $expected = '{"id":null,"order_id":"1221166825","trans_id":null,"auth_code":null,"host_ref_num":null,"response":"Declined","transaction_type":null,"transaction":null,"transaction_security":"MPI fallback","proc_return_code":"99","code":"99","md_status":"0","status":"declined","status_detail":"99","hash":"hashhash","rand":null,"hash_params":null,"hash_params_val":null,"masked_number":null,"amount":"9000","currency":"949","tx_status":null,"eci":null,"cavv":null,"xid":null,"error_code":"99","error_message":"User Gave Up","md_error_message":"User Gave Up","campaign_url":null,"email":"admin@admin.com","extra":null,"all":{"mdstatus":"0","mderrormessage":"User Gave Up","errmsg":"User Gave Up","clientid":"10012345","oid":"1221166825","response":"Error","procreturncode":"99","customeripaddress":"111.222.333.444","orderid":"1221166825","txntype":"sales","terminalprovuserid":"PROVAUT","secure3dhash":"hashhash","mode":"PROD","terminalid":"10012345","txncurrencycode":"949","customeremailaddress":"admin@admin.com","terminaluserid":"PROVAUT","terminalmerchantid":"5220607","secure3dsecuritylevel":"3D","user_id":"1","errorurl":"https:\/\/example.com\/odeme_basarisiz","apiversion":"v0.01","txnamount":"9000","txninstallmentcount":"","successurl":"https:\/\/example.com\/odeme_basarili"}}';
        $method = $this->getMethod('map3DPayResponseData');
        $result1 = $method->invoke($this->pos, $failResponse);
        $this->assertSame($expected, json_encode($result1));
    }

    /**
     * @param                      $order
     * @param CreditCardGarantiPos $card
     * @param GarantiPosAccount    $account
     *
     * @return array
     */
    private function getSampleRegularPaymentXMLData($order, CreditCardGarantiPos $card, GarantiPosAccount $account)
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => GarantiPos::API_VERSION,
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => 'F0641E566B7B98260FD1608D1DF81E8D55461877',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Card'        => [
                'Number'     => $card->getNumber(),
                'ExpireDate' => $card->getExpirationDate(),
                'CVV2'       => $card->getCvv(),
            ],
            'Order'       => [
                'OrderID'     => $order->id,
                'GroupID'     => '',
                'AddressList' => [
                    'Address' => [
                        'Type'        => 'S',
                        'Name'        => $order->name,
                        'LastName'    => '',
                        'Company'     => '',
                        'Text'        => '',
                        'District'    => '',
                        'City'        => '',
                        'PostalCode'  => '',
                        'Country'     => '',
                        'PhoneNumber' => '',
                    ],
                ],
            ],
            'Transaction' => [
                'Type'                  => 'sales',
                'InstallmentCnt'        => $order->installment,
                'Amount'                => $order->amount,
                'CurrencyCode'          => $order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
                'Description'           => '',
                'OriginalRetrefNum'     => '',
            ],
        ];
    }

    /**
     * @param                   $order
     * @param GarantiPosAccount $account
     *
     * @return array
     */
    private function getSampleRegularPostXMLData($order, $account)
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => GarantiPos::API_VERSION,
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '7598B3D1A15C45095CD139E9CFD780B050D1C4AA',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Order'       => [
                'OrderID' => $order->id,
            ],
            'Transaction' => [
                'Type'              => 'postauth',
                'Amount'            => $order->amount,
                'CurrencyCode'      => $order->currency,
                'OriginalRetrefNum' => $order->ref_ret_num,
            ],
        ];
    }

    /**
     * @param                   $order
     * @param GarantiPosAccount $account
     * @param array             $responseData
     *
     * @return array
     */
    private function getSample3DPaymentXMLData($order, $account, array $responseData)
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => GarantiPos::API_VERSION,
            'ChannelCode' => '',
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '7598B3D1A15C45095CD139E9CFD780B050D1C4AA',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $responseData['customeripaddress'],
                'EmailAddress' => $responseData['customeremailaddress'],
            ],
            'Card'        => [
                'Number'     => '',
                'ExpireDate' => '',
                'CVV2'       => '',
            ],
            'Order'       => [
                'OrderID'     => $responseData['orderid'],
                'GroupID'     => '',
                'AddressList' => [
                    'Address' => [
                        'Type'        => 'B',
                        'Name'        => $order->name,
                        'LastName'    => '',
                        'Company'     => '',
                        'Text'        => '',
                        'District'    => '',
                        'City'        => '',
                        'PostalCode'  => '',
                        'Country'     => '',
                        'PhoneNumber' => '',
                    ],
                ],
            ],
            'Transaction' => [
                'Type'                  => $responseData['txntype'],
                'InstallmentCnt'        => $order->installment,
                'Amount'                => $responseData['txnamount'],
                'CurrencyCode'          => $responseData['txncurrencycode'],
                'CardholderPresentCode' => '13',
                'MotoInd'               => 'N',
                'Secure3D'              => [
                    'AuthenticationCode' => $responseData['cavv'],
                    'SecurityLevel'      => $responseData['eci'],
                    'TxnID'              => $responseData['xid'],
                    'Md'                 => $responseData['md'],
                ],
            ],
        ];
    }

    /**
     * @param                   $order
     * @param GarantiPosAccount $account
     *
     * @return array
     */
    private function getSampleStatusXMLData($order, $account)
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => GarantiPos::API_VERSION,
            'ChannelCode' => '',
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '8DD74209DEEB7D333105E1C69998A827419A3B04',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Order'       => [
                'OrderID' => $order->id,
                'GroupID' => '',
            ],
            'Card'        => [
                'Number'     => '',
                'ExpireDate' => '',
                'CVV2'       => '',
            ],
            'Transaction' => [
                'Type'                  => 'orderinq',
                'InstallmentCnt'        => '',
                'Amount'                => $order->amount,
                'CurrencyCode'          => $order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
            ],
        ];
    }

    /**
     * @param                   $order
     * @param GarantiPosAccount $account
     *
     * @return array
     */
    private function getSampleCancelXMLData($order, $account)
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => GarantiPos::API_VERSION,
            'ChannelCode' => '',
            'Terminal'    => [
                'ProvUserID' => $account->getRefundUsername(),
                'UserID'     => $account->getRefundUsername(),
                'HashData'   => '8DD74209DEEB7D333105E1C69998A827419A3B04',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Order'       => [
                'OrderID' => $order->id,
                'GroupID' => '',
            ],
            'Transaction' => [
                'Type'                  => 'void',
                'InstallmentCnt'        => $order->installment,
                'Amount'                => $order->amount,
                'CurrencyCode'          => $order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
                'OriginalRetrefNum'     => $order->ref_ret_num,
            ],
        ];

    }

    /**
     * @param                   $order
     * @param GarantiPosAccount $account
     *
     * @return array
     */
    private function getSampleRefundXMLData($order, $account)
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => GarantiPos::API_VERSION,
            'ChannelCode' => '',
            'Terminal'    => [
                'ProvUserID' => $account->getRefundUsername(),
                'UserID'     => $account->getRefundUsername(),
                'HashData'   => '8DD74209DEEB7D333105E1C69998A827419A3B04',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Order'       => [
                'OrderID' => $order->id,
                'GroupID' => '',
            ],
            'Transaction' => [
                'Type'                  => 'refund',
                'InstallmentCnt'        => '',
                'Amount'                => $order->amount,
                'CurrencyCode'          => $order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
                'OriginalRetrefNum'     => $order->ref_ret_num,
            ],
        ];
    }

    private static function getMethod(string $name): ReflectionMethod
    {
        $class = new ReflectionClass(GarantiPos::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}

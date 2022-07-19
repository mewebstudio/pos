<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\GarantiPos;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * GarantiPosTest
 */
class GarantiPosTest extends TestCase
{
    /** @var GarantiPosAccount */
    private $account;
    private $config;

    /** @var AbstractCreditCard */
    private $card;
    private $order;

    /** @var GarantiPos */
    private $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            AbstractGateway::MODEL_3D_SECURE,
            '12345678',
            'PROVRFN',
            '123qweASD/'
        );

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
        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '21', '12', '122');
    }

    /**
     * @return void
     */
    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
    }

    /**
     * @return void
     */
    public function testPrepare()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());
        $this->assertSame(0, $this->pos->getOrder()->installment);
    }

    /**
     * @return void
     *
     * @uses \Mews\Pos\Gateways\GarantiPos::map3DPayResponseData()
     */
    public function testMap3DPayResponseDataSuccess()
    {
        $gatewayResponse = [
            'xid'                   => 'bVi+A/h6SjXabcde=',
            'mdstatus'              => '1',
            'mderrormessage'        => 'TROY Gateway Result: [Code: \'000\', Message: \'Success\', Description: \'Successful\']',
            'txnstatus'             => '',
            'eci'                   => '',
            'cavv'                  => 'ABIBBDYABBBBBAAABAAAAAAAAAAA=',
            'paressyntaxok'         => '',
            'paresverified'         => '',
            'version'               => '',
            'ireqcode'              => '',
            'ireqdetail'            => '',
            'vendorcode'            => '',
            'cavvalgorithm'         => '',
            'md'                    => 'longstring',
            'terminalid'            => '10012345',
            'oid'                   => '1221513409',
            'authcode'              => '',
            'response'              => '',
            'errmsg'                => '',
            'hostmsg'               => '',
            'procreturncode'        => '',
            'transid'               => '1001513409',
            'hostrefnum'            => '',
            'rnd'                   => 'vNOc4abcde2aCL/HBzs',
            'hash'                  => '1I9zDunx0hashTRI816trOG0Ao0=',
            'hashparams'            => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
            'hashparamsval'         => 'longstring',
            'clientid'              => '10012345',
            'MaskedPan'             => '454311***7965',
            'customeripaddress'     => '134.170.165.149',
            'orderid'               => '1221513409',
            'txntype'               => 'sales',
            'terminalprovuserid'    => 'PROVAUT',
            'secure3dhash'          => 'BE3C507794AhashE021E8EA239415D774EEF2',
            'mode'                  => 'PROD',
            'txncurrencycode'       => '949',
            'customeremailaddress'  => 'admin@admin.com',
            'terminaluserid'        => 'PROVAUT',
            'terminalmerchantid'    => '1234567',
            'secure3dsecuritylevel' => '3D',
            'user_id'               => '1',
            'errorurl'              => 'https://example.com/odeme_basarisiz',
            'apiversion'            => 'v0.01',
            'txnamount'             => '100',
            'txninstallmentcount'   => '',
            'successurl'            => 'https://example.com/odeme_basarili',
        ];
        $expected        = '{"id":"","order_id":"1221513409","trans_id":"1001513409","auth_code":"","host_ref_num":"","response":"Approved","transaction_type":null,"transaction":null,"transaction_security":"Full 3D Secure","proc_return_code":"","code":"","md_status":"1","status":"approved","status_detail":null,"hash":"1I9zDunx0hashTRI816trOG0Ao0=","rand":"vNOc4abcde2aCL\/HBzs","hash_params":"clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:","hash_params_val":"longstring","masked_number":"454311***7965","amount":"100","currency":"949","tx_status":"","eci":"","cavv":"ABIBBDYABBBBBAAABAAAAAAAAAAA=","xid":"bVi+A\/h6SjXabcde=","error_code":null,"error_message":"","md_error_message":"TROY Gateway Result: [Code: \'000\', Message: \'Success\', Description: \'Successful\']","campaign_url":null,"email":"admin@admin.com","extra":null,"3d_all":{"xid":"bVi+A\/h6SjXabcde=","mdstatus":"1","mderrormessage":"TROY Gateway Result: [Code: \'000\', Message: \'Success\', Description: \'Successful\']","txnstatus":"","eci":"","cavv":"ABIBBDYABBBBBAAABAAAAAAAAAAA=","paressyntaxok":"","paresverified":"","version":"","ireqcode":"","ireqdetail":"","vendorcode":"","cavvalgorithm":"","md":"longstring","terminalid":"10012345","oid":"1221513409","authcode":"","response":"","errmsg":"","hostmsg":"","procreturncode":"","transid":"1001513409","hostrefnum":"","rnd":"vNOc4abcde2aCL\/HBzs","hash":"1I9zDunx0hashTRI816trOG0Ao0=","hashparams":"clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:","hashparamsval":"longstring","clientid":"10012345","MaskedPan":"454311***7965","customeripaddress":"134.170.165.149","orderid":"1221513409","txntype":"sales","terminalprovuserid":"PROVAUT","secure3dhash":"BE3C507794AhashE021E8EA239415D774EEF2","mode":"PROD","txncurrencycode":"949","customeremailaddress":"admin@admin.com","terminaluserid":"PROVAUT","terminalmerchantid":"1234567","secure3dsecuritylevel":"3D","user_id":"1","errorurl":"https:\/\/example.com\/odeme_basarisiz","apiversion":"v0.01","txnamount":"100","txninstallmentcount":"","successurl":"https:\/\/example.com\/odeme_basarili"}}';
        $method          = $this->getMethod('map3DPayResponseData');
        $result1         = $method->invoke($this->pos, $gatewayResponse);

        $this->assertIsArray($result1);
        $this->assertSame(json_decode($expected, true), $result1);
    }

    /**
     * @return void
     *
     * @uses \Mews\Pos\Gateways\GarantiPos::map3DPayResponseData()
     */
    public function testMap3DPayResponseDataFail()
    {
        $failResponse = [
            'mdstatus'              => '0',
            'mderrormessage'        => 'User Gave Up',
            'errmsg'                => 'User Gave Up',
            'clientid'              => '10012345',
            'oid'                   => '1221166825',
            'response'              => 'Error',
            'procreturncode'        => '99',
            'customeripaddress'     => '111.222.333.444',
            'orderid'               => '1221166825',
            'txntype'               => 'sales',
            'terminalprovuserid'    => 'PROVAUT',
            'hash'                  => 'yTSvdQilq/l/SSQpO4mBJxFCJIs=',
            'secure3dhash'          => 'hashhash',
            'mode'                  => 'PROD',
            'terminalid'            => '10012345',
            'txncurrencycode'       => '949',
            'customeremailaddress'  => 'admin@admin.com',
            'terminaluserid'        => 'PROVAUT',
            'terminalmerchantid'    => '5220607',
            'secure3dsecuritylevel' => '3D',
            'user_id'               => '1',
            'errorurl'              => 'https://example.com/odeme_basarisiz',
            'apiversion'            => 'v0.01',
            'txnamount'             => '9000',
            'txninstallmentcount'   => '',
            'successurl'            => 'https://example.com/odeme_basarili',
        ];
        $expected     = '{"id":null,"order_id":"1221166825","trans_id":null,"auth_code":null,"host_ref_num":null,"response":"Declined","transaction_type":null,"transaction":null,"transaction_security":"MPI fallback","proc_return_code":"99","code":"99","md_status":"0","status":"declined","status_detail":"99","hash":"yTSvdQilq/l/SSQpO4mBJxFCJIs=","rand":null,"hash_params":null,"hash_params_val":null,"masked_number":null,"amount":"9000","currency":"949","tx_status":null,"eci":null,"cavv":null,"xid":null,"error_code":"99","error_message":"User Gave Up","md_error_message":"User Gave Up","campaign_url":null,"email":"admin@admin.com","extra":null,"3d_all":{"mdstatus":"0","mderrormessage":"User Gave Up","errmsg":"User Gave Up","clientid":"10012345","oid":"1221166825","response":"Error","procreturncode":"99","customeripaddress":"111.222.333.444","orderid":"1221166825","txntype":"sales","terminalprovuserid":"PROVAUT","hash":"yTSvdQilq/l/SSQpO4mBJxFCJIs=","secure3dhash":"hashhash","mode":"PROD","terminalid":"10012345","txncurrencycode":"949","customeremailaddress":"admin@admin.com","terminaluserid":"PROVAUT","terminalmerchantid":"5220607","secure3dsecuritylevel":"3D","user_id":"1","errorurl":"https:\/\/example.com\/odeme_basarisiz","apiversion":"v0.01","txnamount":"9000","txninstallmentcount":"","successurl":"https:\/\/example.com\/odeme_basarili"}}';
        $method       = $this->getMethod('map3DPayResponseData');
        $result1      = $method->invoke($this->pos, $failResponse);
        $this->assertSame(json_decode($expected, true), $result1);
    }

    /**
     * @return void
     */
    public function testCheck3DHash()
    {
        $data = [
            'mdstatus'              => '1',
            'eci'                   => '02',
            'cavv'                  => 'jCm0m+u/0hUfAREHBAMBcfN+pSo=',
            'md'                    => 'WnSgn5zoQegm4jJvQhQdor+UOT6z+QkIZ3R9y3vMs39AprOcGRdxi3TuHU9YaNYklgFLN+1t097EwC6+FXq7Hr2xiE98N2LcY9zaAbt1JdU3DHKyDh6mQH/QZZhVYoq9gg9mmxlGbElKlnbduNx4zj0c0vEoq9mj',
            'oid'                   => '22061505230002_8EEB',
            'authcode'              => '',
            'procreturncode'        => '',
            'response'              => '',
            'rnd'                   => 'VU6XvBbJr1QeyBu6g4Jg',
            'hash'                  => 'yTSvdQilq/l/SSQpO4mBJxFCJIs=',
            'hashparams'            => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
            'hashparamsval'         => '3069129722061505230002_8EEB1jCm0m+u/0hUfAREHBAMBcfN+pSo=02WnSgn5zoQegm4jJvQhQdor+UOT6z+QkIZ3R9y3vMs39AprOcGRdxi3TuHU9YaNYklgFLN+1t097EwC6+FXq7Hr2xiE98N2LcY9zaAbt1JdU3DHKyDh6mQH/QZZhVYoq9gg9mmxlGbElKlnbduNx4zj0c0vEoq9mjVU6XvBbJr1QeyBu6g4Jg',
            'clientid'              => '30691297',
            'secure3dhash'          => 'D9E8323BC9E19867E615F72C7C70D01ED44C7576',
        ];

        $this->assertTrue($this->pos->check3DHash($data));

        $data['mdstatus'] = '';
        $this->assertFalse($this->pos->check3DHash($data));
    }

    /**
     * @return void
     *
     * @uses \Mews\Pos\Gateways\GarantiPos::map3DPaymentData()
     */
    public function testMap3DPaymentAuthSuccessPaymentFail()
    {
        $threeDResponse = [
            'xid'                   => 'RszfrwEYe/8xb7rnrPuh6C9pZSQ=',
            'mdstatus'              => '1',
            'mderrormessage'        => 'Authenticated',
            'txnstatus'             => 'Y',
            'eci'                   => '02',
            'cavv'                  => 'jCm0m+u/0hUfAREHBAMBcfN+pSo=',
            'paressyntaxok'         => 'true',
            'paresverified'         => 'true',
            'version'               => '2.0',
            'ireqcode'              => '',
            'ireqdetail'            => '',
            'vendorcode'            => '',
            'cavvalgorithm'         => '3',
            'md'                    => 'WnSgn5zoQegm4jJvQhQdor+UOT6z+QkIZ3R9y3vMs39AprOcGRdxi3TuHU9YaNYklgFLN+1t097EwC6+FXq7Hr2xiE98N2LcY9zaAbt1JdU3DHKyDh6mQH/QZZhVYoq9gg9mmxlGbElKlnbduNx4zj0c0vEoq9mj',
            'terminalid'            => '30691297',
            'oid'                   => '22061505230002_8EEB',
            'authcode'              => '',
            'response'              => '',
            'errmsg'                => '',
            'hostmsg'               => '',
            'procreturncode'        => '',
            'transid'               => '22061505230002_8EEB',
            'hostrefnum'            => '',
            'rnd'                   => 'VU6XvBbJr1QeyBu6g4Jg',
            'hash'                  => 'yTSvdQilq/l/SSQpO4mBJxFCJIs=',
            'hashparams'            => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
            'hashparamsval'         => '3069129722061505230002_8EEB1jCm0m+u/0hUfAREHBAMBcfN+pSo=02WnSgn5zoQegm4jJvQhQdor+UOT6z+QkIZ3R9y3vMs39AprOcGRdxi3TuHU9YaNYklgFLN+1t097EwC6+FXq7Hr2xiE98N2LcY9zaAbt1JdU3DHKyDh6mQH/QZZhVYoq9gg9mmxlGbElKlnbduNx4zj0c0vEoq9mjVU6XvBbJr1QeyBu6g4Jg',
            'clientid'              => '30691297',
            'MaskedPan'             => '4050908481',
            'apiversion'            => 'v0.01',
            'orderid'               => '22061505230002_8EEB',
            'txninstallmentcount'   => '',
            'terminaluserid'        => 'PROVAUT',
            'secure3dhash'          => 'D9E8323BC9E19867E615F72C7C70D01ED44C7576',
            'secure3dsecuritylevel' => '3D',
            'txncurrencycode'       => '949',
            'customeremailaddress'  => 'test@test.com',
            'errorurl'              => 'https://example.com/gs2',
            'terminalmerchantid'    => '7000679',
            'mode'                  => 'TEST',
            'terminalprovuserid'    => 'PROVAUT',
            'txnamount'             => '10000',
            'successurl'            => 'https://example.com/gs1',
            'customeripaddress'     => '188.119.3.229',
            'txntype'               => 'sales',
        ];

        $paymentResponse = [
            'Mode'        => '',
            'Terminal'    => [
                'ProvUserID' => 'PROVAUT',
                'UserID'     => 'PROVAUT',
                'ID'         => '30691297',
                'MerchantID' => '7000679',
            ],
            'Customer'    => [
                'IPAddress'    => '188.119.3.229',
                'EmailAddress' => 'test@test.com',
            ],
            'Order'       => [
                'OrderID' => '22061505230002_8EEB',
                'GroupID' => '',
            ],
            'Transaction' => [
                'Response'         => [
                    'Source'     => 'HOST',
                    'Code'       => '58',
                    'ReasonCode' => '58',
                    'Message'    => 'Declined',
                    'ErrorMsg'   => '\u0130\u015fleminizi ger\u00e7ekle\u015ftiremiyoruz.Tekrar deneyiniz',
                    'SysErrMsg'  => '15-015-SON KULLANIM TARIHI GECMIS',
                ],
                'RetrefNum'        => '216607526514',
                'AuthCode'         => '',
                'BatchNum'         => '004651',
                'SequenceNum'      => '002321',
                'ProvDate'         => '20220615 17:23:52',
                'CardNumberMasked' => '405090******8481',
                'CardHolderName'   => '',
                'CardType'         => 'PREPAID',
                'HashData'         => 'CB1A8579B8E7F0F612E6339E99507B18F18BB0C0',
                'HostMsgList'      => '',
                'RewardInqResult'  => [
                    'RewardList' => '',
                    'ChequeList' => '',
                ],
                'GarantiCardInd'   => 'Y',
            ],
        ];
        $paymentResponse = json_encode($paymentResponse);
        $paymentResponse = json_decode($paymentResponse);
        $method          = $this->getMethod('map3DPaymentData');
        $result1         = $method->invoke($this->pos, $threeDResponse, $paymentResponse);

        $this->assertIsArray($result1);
        $this->assertSame('declined', $result1['status']);
        $this->assertSame('Declined', $result1['response']);
        $this->assertSame('22061505230002_8EEB', $result1['order_id']);
        $this->assertSame('', $result1['auth_code']);
        $this->assertSame('216607526514', $result1['host_ref_num']);
        $this->assertSame('1', $result1['md_status']);
        $this->assertSame('10000', $result1['amount']);
        //todo should be TRY
        $this->assertSame('949', $result1['currency']);
        $this->assertSame('Authenticated', $result1['md_error_message']);
    }

    private static function getMethod(string $name): ReflectionMethod
    {
        $class  = new ReflectionClass(GarantiPos::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}

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
        $this->assertSame(10025, $this->pos->getOrder()->amount);
        $this->assertSame('949', $this->pos->getOrder()->currency);
        $this->assertSame('', $this->pos->getOrder()->installment);
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
        $expected        = '{"id":"","order_id":"1221513409","trans_id":"1001513409","auth_code":"","host_ref_num":"","response":"Approved","transaction_type":null,"transaction":null,"transaction_security":"Full 3D Secure","proc_return_code":"","code":"","md_status":"1","status":"approved","status_detail":null,"hash":"BE3C507794AhashE021E8EA239415D774EEF2","rand":"vNOc4abcde2aCL\/HBzs","hash_params":"clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:","hash_params_val":"longstring","masked_number":"454311***7965","amount":"100","currency":"949","tx_status":"","eci":"","cavv":"ABIBBDYABBBBBAAABAAAAAAAAAAA=","xid":"bVi+A\/h6SjXabcde=","error_code":null,"error_message":"","md_error_message":"TROY Gateway Result: [Code: \'000\', Message: \'Success\', Description: \'Successful\']","campaign_url":null,"email":"admin@admin.com","extra":null,"3d_all":{"xid":"bVi+A\/h6SjXabcde=","mdstatus":"1","mderrormessage":"TROY Gateway Result: [Code: \'000\', Message: \'Success\', Description: \'Successful\']","txnstatus":"","eci":"","cavv":"ABIBBDYABBBBBAAABAAAAAAAAAAA=","paressyntaxok":"","paresverified":"","version":"","ireqcode":"","ireqdetail":"","vendorcode":"","cavvalgorithm":"","md":"longstring","terminalid":"10012345","oid":"1221513409","authcode":"","response":"","errmsg":"","hostmsg":"","procreturncode":"","transid":"1001513409","hostrefnum":"","rnd":"vNOc4abcde2aCL\/HBzs","hash":"1I9zDunx0hashTRI816trOG0Ao0=","hashparams":"clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:","hashparamsval":"longstring","clientid":"10012345","MaskedPan":"454311***7965","customeripaddress":"134.170.165.149","orderid":"1221513409","txntype":"sales","terminalprovuserid":"PROVAUT","secure3dhash":"BE3C507794AhashE021E8EA239415D774EEF2","mode":"PROD","txncurrencycode":"949","customeremailaddress":"admin@admin.com","terminaluserid":"PROVAUT","terminalmerchantid":"1234567","secure3dsecuritylevel":"3D","user_id":"1","errorurl":"https:\/\/example.com\/odeme_basarisiz","apiversion":"v0.01","txnamount":"100","txninstallmentcount":"","successurl":"https:\/\/example.com\/odeme_basarili"}}';
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
        $expected     = '{"id":null,"order_id":"1221166825","trans_id":null,"auth_code":null,"host_ref_num":null,"response":"Declined","transaction_type":null,"transaction":null,"transaction_security":"MPI fallback","proc_return_code":"99","code":"99","md_status":"0","status":"declined","status_detail":"99","hash":"hashhash","rand":null,"hash_params":null,"hash_params_val":null,"masked_number":null,"amount":"9000","currency":"949","tx_status":null,"eci":null,"cavv":null,"xid":null,"error_code":"99","error_message":"User Gave Up","md_error_message":"User Gave Up","campaign_url":null,"email":"admin@admin.com","extra":null,"3d_all":{"mdstatus":"0","mderrormessage":"User Gave Up","errmsg":"User Gave Up","clientid":"10012345","oid":"1221166825","response":"Error","procreturncode":"99","customeripaddress":"111.222.333.444","orderid":"1221166825","txntype":"sales","terminalprovuserid":"PROVAUT","secure3dhash":"hashhash","mode":"PROD","terminalid":"10012345","txncurrencycode":"949","customeremailaddress":"admin@admin.com","terminaluserid":"PROVAUT","terminalmerchantid":"5220607","secure3dsecuritylevel":"3D","user_id":"1","errorurl":"https:\/\/example.com\/odeme_basarisiz","apiversion":"v0.01","txnamount":"9000","txninstallmentcount":"","successurl":"https:\/\/example.com\/odeme_basarili"}}';
        $method       = $this->getMethod('map3DPayResponseData');
        $result1      = $method->invoke($this->pos, $failResponse);
        $this->assertSame(json_decode($expected, true), $result1);
    }

    private static function getMethod(string $name): ReflectionMethod
    {
        $class  = new ReflectionClass(GarantiPos::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}

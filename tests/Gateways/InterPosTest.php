<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\InterPos;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\InterPos
 */
class InterPosTest extends TestCase
{
    /** @var InterPosAccount */
    private $account;
    /** @var InterPos */
    private $pos;
    /** @var AbstractCreditCard */
    private $card;

    private $config;
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $userCode     = 'InterTestApi';
        $userPass     = '3';
        $shopCode     = '3123';
        $merchantPass = 'gDg1N';

        $this->account = AccountFactory::createInterPosAccount(
            'denizbank',
            $shopCode,
            $userCode,
            $userPass,
            AbstractGateway::MODEL_3D_SECURE,
            $merchantPass
        );

        $this->order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => AbstractGateway::LANG_TR,
            'rand'        => microtime(true),
        ];

        $this->pos = PosFactory::createPosGateway($this->account);

        $this->pos->setTestMode(true);
        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '21', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
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
    }

    /**
     * @return void
     */
    public function testCheck3DHash()
    {
        $data = [
            'Version'        => '',
            'PurchAmount'    => 320,
            'Exponent'       => '',
            'Currency'       => '949',
            'OkUrl'          => 'https://localhost/pos/examples/interpos/3d/success.php',
            'FailUrl'        => 'https://localhost/pos/examples/interpos/3d/fail.php',
            'MD'             => '',
            'OrderId'        => '20220327140D',
            'ProcReturnCode' => '81',
            'Response'       => '',
            'mdStatus'       => '0',
            'HASH'           => '9DZVckklZFjuoA7sl4MN0l7VDMo=',
            'HASHPARAMS'     => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
            'HASHPARAMSVAL'  => '320949https://localhost/pos/examples/interpos/3d/success.phphttps://localhost/pos/examples/interpos/3d/fail.php20220327140D810',
        ];

        $this->assertTrue($this->pos->check3DHash($this->account, $data));

        $data['mdStatus'] = '';
        $this->assertFalse($this->pos->check3DHash($this->account, $data));
    }

    /**
     * @return void
     */
    public function testMake3DPaymentAuthFail()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $request = Request::create('', 'POST', [
            'Version' => '',
            'MerchantID' => '',
            'ShopCode' => '3123',
            'TxnStat' => 'N',
            'MD' => '',
            'RetCode' => '',
            'RetDet' => '',
            'VenderCode' => '',
            'Eci' => '',
            'PayerAuthenticationCode' => '',
            'PayerTxnId' => '',
            'CavvAlg' => '',
            'PAResVerified' => 'False',
            'PAResSyntaxOK' => 'False',
            'Expiry' => '****',
            'Pan' => '409070******0057',
            'OrderId' => '202204155912',
            'PurchAmount' => '30',
            'Exponent' => '',
            'Description' => '',
            'Description2' => '',
            'Currency' => '949',
            'OkUrl' => 'http://localhost/interpos/3d/response.php',
            'FailUrl' => 'http://localhost/interpos/3d/response.php',
            '3DStatus' => '0',
            'AuthCode' => '',
            'HostRefNum' => 'hostid',
            'TransId' => '',
            'TRXDATE' => '',
            'CardHolderName' => '',
            'mdStatus' => '0',
            'ProcReturnCode' => '81',
            'TxnResult' => '',
            'ErrorMessage' => 'Terminal Aktif Degil',
            'ErrorCode' => 'B810002',
            'Response' => '',
            'HASH' => '4hSLIFy/RNlEdB7sUYNnP7kAqzM=',
            'HASHPARAMS' => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
            'HASHPARAMSVAL' => '30949http://localhost/interpos/3d/response.phphttp://localhost/interpos/3d/response.php202204155912810',
        ]);

        $this->pos->make3DPayment($request);
        $result = $this->pos->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;

        $this->assertSame('declined', $result['status']);
        $this->assertSame('81', $result['proc_return_code']);
        $this->assertSame('B810002', $result['error_code']);
        $this->assertSame('202204155912', $result['order_id']);
        $this->assertSame('Auth', $result['transaction']);
        $this->assertSame('409070******0057', $result['masked_number']);
        $this->assertSame('30', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('4hSLIFy/RNlEdB7sUYNnP7kAqzM=', $result['hash']);
        $this->assertSame('Terminal Aktif Degil', $result['error_message']);
        $this->assertNotEmpty($result['3d_all']);
        $this->assertEmpty($result['all']);
    }

    /**
     * @return void
     */
    public function testMake3DPayPaymentFail()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $request = Request::create('', 'POST', [
            'Version' => '',
            'MerchantID' => '',
            'ShopCode' => '3123',
            'TxnStat' => 'N',
            'MD' => '',
            'RetCode' => '',
            'RetDet' => '',
            'VenderCode' => '',
            'Eci' => '',
            'PayerAuthenticationCode' => '',
            'PayerTxnId' => '',
            'CavvAlg' => '',
            'PAResVerified' => 'False',
            'PAResSyntaxOK' => 'False',
            'Expiry' => '****',
            'Pan' => '409070******0057',
            'OrderId' => '202204155912',
            'PurchAmount' => '30',
            'Exponent' => '',
            'Description' => '',
            'Description2' => '',
            'Currency' => '949',
            'OkUrl' => 'http://localhost/interpos/3d-pay/response.php',
            'FailUrl' => 'http://localhost/interpos/3d-pay/response.php',
            '3DStatus' => '0',
            'AuthCode' => '',
            'HostRefNum' => 'hostid',
            'TransId' => '',
            'TRXDATE' => '',
            'CardHolderName' => '',
            'mdStatus' => '0',
            'ProcReturnCode' => '81',
            'TxnResult' => '',
            'ErrorMessage' => 'Terminal Aktif Degil',
            'ErrorCode' => 'B810002',
            'Response' => '',
            'HASH' => 'klXFUEWTgMc6pRZJFsQRMTOa9us=',
            'HASHPARAMS' => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
            'HASHPARAMSVAL' => '30949http://localhost/interpos/3d-pay/response.phphttp://localhost/interpos/3d-pay/response.php20220415D7F8810',
        ]);

        $this->pos->make3DPayment($request);
        $result = $this->pos->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;

        $this->assertSame('declined', $result['status']);
        $this->assertSame('81', $result['proc_return_code']);
        $this->assertSame('B810002', $result['error_code']);
        $this->assertSame('202204155912', $result['order_id']);
        $this->assertSame('Auth', $result['transaction']);
        $this->assertSame('409070******0057', $result['masked_number']);
        $this->assertSame('30', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('klXFUEWTgMc6pRZJFsQRMTOa9us=', $result['hash']);
        $this->assertSame('Terminal Aktif Degil', $result['error_message']);
        $this->assertNotEmpty($result['3d_all']);
        $this->assertEmpty($result['all']);
    }
}

<?php

namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCardEstPos;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\EstPos;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class EstPostTest extends TestCase
{
    /**
     * @var EstPosAccount
     */
    private $account;
    /**
     * @var EstPos
     */
    private $pos;
    private $config;

    /**
     * @var CreditCardEstPos
     */
    private $card;
    private $order;
    /**
     * @var XmlEncoder
     */
    private $xmlDecoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createEstPosAccount(
            'akbank',
            '700655000200',
            'ISBANKAPI',
            'ISBANK07',
            AbstractGateway::MODEL_3D_SECURE,
            'TRPS0200',
            EstPos::LANG_TR
        );

        $this->card = new CreditCardEstPos('5555444433332222', '21', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);

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

    public function testMapRecurringFrequency()
    {
        $this->assertEquals('M', $this->pos->mapRecurringFrequency('MONTH'));
        $this->assertEquals('M', $this->pos->mapRecurringFrequency('M'));
    }

    public function testGet3DFormWithCardData()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $form = [
            'gateway' => $this->config['banks'][$this->account->getBank()]['urls']['gateway']['test'],
            'inputs'  => [
                'clientid'                        => $this->account->getClientId(),
                'storetype'                       => $this->account->getModel(),
                'hash'                            => $this->pos->create3DHash($this->pos->getAccount(), $this->pos->getOrder(), 'Auth'),
                'cardType'                        => $this->card->getCardCode(),
                'pan'                             => $this->card->getNumber(),
                'Ecom_Payment_Card_ExpDate_Month' => $this->card->getExpireMonth(),
                'Ecom_Payment_Card_ExpDate_Year'  => $this->card->getExpireYear(),
                'cv2'                             => $this->card->getCvv(),
                'firmaadi'                        => $this->order['name'],
                'Email'                           => $this->order['email'],
                'amount'                          => $this->order['amount'],
                'oid'                             => $this->order['id'],
                'okUrl'                           => $this->order['success_url'],
                'failUrl'                         => $this->order['fail_url'],
                'rnd'                             => $this->order['rand'],
                'lang'                            => $this->order['lang'],
                'currency'                        => 949,
            ],
        ];
        $this->assertEquals($form, $this->pos->get3DFormData());
    }

    public function testGet3DFormWithoutCardData()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY);

        $form = [
            'gateway' => $this->config['banks'][$this->account->getBank()]['urls']['gateway']['test'],
            'inputs'  => [
                'clientid'  => $this->account->getClientId(),
                'storetype' => $this->account->getModel(),
                'hash'      => $this->pos->create3DHash($this->pos->getAccount(), $this->pos->getOrder(), 'Auth'),
                'firmaadi'  => $this->order['name'],
                'Email'     => $this->order['email'],
                'amount'    => $this->order['amount'],
                'oid'       => $this->order['id'],
                'okUrl'     => $this->order['success_url'],
                'failUrl'   => $this->order['fail_url'],
                'rnd'       => $this->order['rand'],
                'lang'      => $this->order['lang'],
                'currency'  => 949,
            ],
        ];
        $this->assertEquals($form, $this->pos->get3DFormData());
    }

    public function testGet3DHostFormData()
    {
        $account = AccountFactory::createEstPosAccount(
            'akbank',
            'XXXXXXX',
            'XXXXXXX',
            'XXXXXXX',
            AbstractGateway::MODEL_3D_HOST,
            'VnM5WZ3sGrPusmWP',
            EstPos::LANG_TR
        );
        $pos = PosFactory::createPosGateway($account);
        $pos->setTestMode(true);

        $pos->prepare($this->order, AbstractGateway::TX_PAY);

        $form = [
            'gateway' => $this->config['banks'][$account->getBank()]['urls']['gateway']['test'],
            'inputs'  => [
                'clientid'  => $account->getClientId(),
                'storetype' => $account->getModel(),
                'hash'      => $pos->create3DHash($pos->getAccount(), $pos->getOrder(), 'Auth'),
                'firmaadi'  => $this->order['name'],
                'Email'     => $this->order['email'],
                'amount'    => $this->order['amount'],
                'oid'       => $this->order['id'],
                'okUrl'     => $this->order['success_url'],
                'failUrl'   => $this->order['fail_url'],
                'rnd'       => $this->order['rand'],
                'lang'      => $this->order['lang'],
                'currency'  => 949,
                'islemtipi'  => 'Auth',
                'taksit'    => $this->order['installment'],
            ],
        ];
        $this->assertEquals($form, $pos->get3DFormData());
    }

    public function testCheck3DHash()
    {
        $data = $this->get3DMakePaymentFailResponseData();
        $this->assertTrue($this->pos->check3DHash($data));

        $data['mdStatus'] = '';
        $this->assertFalse($this->pos->check3DHash($data));
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
            'installment' => '0',
            'currency'    => 'TRY',
        ];


        $card = new CreditCardEstPos('5555444433332222', '22', '01', '123', 'ahmet');
        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_PAY, $card);

        $actualXML = $pos->createRegularPaymentXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRegularPaymentXMLData($pos->getOrder(), $pos->getCard(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
    }

    public function testCreateRegularPostXML()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_POST_PAY);

        $actualXML = $pos->createRegularPostXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRegularPostXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
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
            'md'   => '1',
            'xid'  => '100000005xid',
            'eci'  => '100000005eci',
            'cavv' => 'cavv',
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actualXML = $pos->create3DPaymentXML($responseData);
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSample3DPaymentXMLData($pos->getOrder(), $pos->getAccount(), $responseData);
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
    }

    public function testCreate3DPaymentXMLForRecurringOrder()
    {

        $order = [
            'id'                        => '2020110828BC',
            'email'                     => 'samp@iexample.com',
            'name'                      => 'john doe',
            'user_id'                   => '1535',
            'ip'                        => '192.168.1.0',
            'amount'                    => 100.01,
            'installment'               => '0',
            'currency'                  => 'TRY',
            'success_url'               => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'                  => 'http://localhost/finansbank-payfor/3d/response.php',
            'recurringFrequency'        => 3,
            'recurringFrequencyType'    => 'MONTH',
            'recurringInstallmentCount' => 4,
        ];

        $responseData = [
            'md'   => '1',
            'xid'  => '100000005xid',
            'eci'  => '100000005eci',
            'cavv' => 'cavv',
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actualXML = $pos->create3DPaymentXML($responseData);
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSample3DPaymentXMLData($pos->getOrder(), $pos->getAccount(), $responseData);
        $this->assertEquals($expectedData, $actualData);
        $this->assertEquals($expectedData['PbOrder'], $actualData['PbOrder']);
    }

    public function testCreateStatusXML()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_STATUS);

        $actualXML = $pos->createStatusXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleStatusXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
    }


    public function testCreateCancelXML()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_CANCEL);

        $actualXML = $pos->createCancelXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleCancelXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
    }

    public function testCreateRefundXML()
    {
        $order = [
            'id'     => '2020110828BC',
            'amount' => 50,
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_REFUND);

        $actualXML = $pos->createRefundXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRefundXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
    }

    public function testMapCancelResponse()
    {
        $gatewayResponse = [
            'OrderId' => rand(),
            'GroupId' => rand(1, 100),
            'Response' => rand(1, 100),
            'AuthCode' => rand(1, 100),
            'HostRefNum' => rand(1, 100),
            'ProcReturnCode' => rand(1, 100),
            'TransId' => rand(1, 100),
            'Extra' => null,
            'ErrMsg' => null,
        ];
        $mapCancelResponseFunc = $this->getProtectedMethod('mapCancelResponse');
        $pos = PosFactory::createPosGateway($this->account);

        $canceledResult = $mapCancelResponseFunc->invokeArgs($pos, [json_decode(json_encode($gatewayResponse))]);
        $this->assertNotEmpty($canceledResult);

        $this->assertSame($gatewayResponse['OrderId'], $canceledResult->order_id);
        $this->assertSame($gatewayResponse['GroupId'], $canceledResult->group_id);
        $this->assertSame($gatewayResponse['Response'], $canceledResult->response);
        $this->assertSame($gatewayResponse['AuthCode'], $canceledResult->auth_code);
        $this->assertSame($gatewayResponse['HostRefNum'], $canceledResult->host_ref_num);
        $this->assertSame($gatewayResponse['ProcReturnCode'], $canceledResult->proc_return_code);
        $this->assertSame($gatewayResponse['TransId'], $canceledResult->trans_id);
        $this->assertSame(null, $canceledResult->error_code);
        $this->assertSame(null, $canceledResult->error_message);
        $this->assertSame('declined', $canceledResult->status);
    }

    /**
     * @return void
     */
    public function testCreate3DHashFor3DSecure()
    {
        $this->order['rand'] = 'rand';

        $account = AccountFactory::createEstPosAccount(
            'akbank',
            'XXXXXXX',
            'XXXXXXX',
            'XXXXXXX',
            AbstractGateway::MODEL_3D_SECURE,
            'VnM5WZ3sGrPusmWP'
        );
        $pos     = PosFactory::createPosGateway($account);

        $expected = '3Wb9YCz1uz3OCFHEI0u2Djga294=';
        $pos->prepare($this->order, AbstractGateway::TX_PAY);
        $actual = $pos->create3DHash($account, $pos->getOrder(), 'Auth');
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DHashForNon3DSecure()
    {
        $this->order['rand'] = 'rand';

        $account  = AccountFactory::createEstPosAccount(
            'akbank',
            'XXXXXXX',
            'XXXXXXX',
            'XXXXXXX',
            AbstractGateway::MODEL_3D_PAY,
            'VnM5WZ3sGrPusmWP'
        );
        $pos      = PosFactory::createPosGateway($account);
        $expected = 'zW2HEQR/H0mpo1jrztIgmIPFFEU=';
        $pos->prepare($this->order, AbstractGateway::TX_PAY);
        $actual = $pos->create3DHash($account, $pos->getOrder(), 'Auth');
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testMake3DPaymentAuthFail()
    {
        $request = Request::create('', 'POST', $this->get3DMakePaymentFailResponseData());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, []])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->expects($this->never())->method('send');
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $posMock->make3DPayment($request);
        $result = $posMock->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('declined', $result['status']);
        $this->assertSame('Not authenticated', $result['md_error_message']);
        $this->assertSame('202204171C44', $result['order_id']);
        $this->assertSame('0', $result['md_status']);
        $this->assertSame('e5KcIY797JNvjrkWjZSfHOa+690=', $result['hash']);
        $this->assertSame('4355 08** **** 4358', $result['masked_number']);
        $this->assertSame('12', $result['month']);
        $this->assertSame('30', $result['year']);
        $this->assertSame('1.01', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('Auth', $result['transaction_type']);
        $this->assertSame(null, $result['auth_code']);
        $this->assertSame(null, $result['host_ref_num']);
        $this->assertSame(null, $result['status_detail']);
        $this->assertSame(null, $result['error_code']);
        $this->assertNotEmpty($result['3d_all']);
    }

    /**
     * @return void
     */
    public function testMake3DPaymentAuthSuccessAndPaymentFail()
    {
        $request = Request::create('', 'POST', $this->get3DMakePaymentAuthSuccessResponseData());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, []])
            ->onlyMethods(['send', 'check3DHash', 'create3DPaymentXML', 'getProcReturnCode'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn((object) $this->get3DMakePaymentPaymentFailResponseData());
        $posMock->expects($this->once())->method('check3DHash')->willReturn(true);
        $posMock->expects($this->any())->method('getProcReturnCode')->willReturn('99');
        $posMock->expects($this->once())->method('create3DPaymentXML')->willReturn('');

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $posMock->make3DPayment($request);
        $result = $posMock->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('declined', $result['status']);
        $this->assertSame(null, $result['md_error_message']);
        $this->assertSame('202204171C63', $result['order_id']);
        $this->assertSame('1', $result['md_status']);
        $this->assertSame('7nVDw9NnL7z4KvTQB7fWhtN8ivQ=', $result['hash']);
        $this->assertSame('4355 08** **** 4358', $result['masked_number']);
        $this->assertSame('12', $result['month']);
        $this->assertSame('30', $result['year']);
        $this->assertSame('1.01', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('Auth', $result['transaction_type']);
        $this->assertSame(null, $result['auth_code']);
        $this->assertSame(null, $result['host_ref_num']);
        $this->assertSame('general_error', $result['status_detail']);
        $this->assertSame('CORE-2001', $result['error_code']);
        $this->assertNotEmpty($result['3d_all']);
    }

    /**
     * @return void
     */
    public function testMake3DPaymentAuthSuccessAndPaymentSuccess()
    {
        $request = Request::create('', 'POST', $this->get3DMakePaymentAuthSuccessResponseData());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, []])
            ->onlyMethods(['send', 'check3DHash', 'create3DPaymentXML', 'getProcReturnCode'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn((object) $this->get3DMakePaymentPaymentSuccessResponseData());
        $posMock->expects($this->once())->method('check3DHash')->willReturn(true);
        $posMock->expects($this->any())->method('getProcReturnCode')->willReturn('00');
        $posMock->expects($this->once())->method('create3DPaymentXML')->willReturn('');

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $posMock->make3DPayment($request);
        $result = $posMock->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('approved', $result['status']);
        $this->assertSame(null, $result['md_error_message']);
        $this->assertSame('202204171C63', $result['order_id']);
        $this->assertSame('1', $result['md_status']);
        $this->assertSame('7nVDw9NnL7z4KvTQB7fWhtN8ivQ=', $result['hash']);
        $this->assertSame('4355 08** **** 4358', $result['masked_number']);
        $this->assertSame('12', $result['month']);
        $this->assertSame('30', $result['year']);
        $this->assertSame('1.01', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('Auth', $result['transaction_type']);
        $this->assertSame('P65781', $result['auth_code']);
        $this->assertSame('210700616852', $result['host_ref_num']);
        $this->assertSame('approved', $result['status_detail']);
        $this->assertSame(null, $result['error_code']);
        $this->assertNotEmpty($result['3d_all']);
    }

    /**
     * @return void
     */
    public function testMake3DHostPaymentSuccess()
    {
        $request = Request::create('', 'POST', $this->get3DHostPaymentSuccessResponseData());

        $pos = $this->pos;
        $pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $pos->make3DHostPayment($request);
        $result = $pos->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('approved', $result['status']);
        $this->assertSame(null, $result['md_error_message']);
        $this->assertSame('20220417FA2D', $result['order_id']);
        $this->assertSame('1', $result['md_status']);
        $this->assertSame('bQY4zwZrjrlZmJWdRdDYgqCvMRU=', $result['hash']);
        $this->assertSame('4355 08** **** 4358', $result['masked_number']);
        $this->assertSame('12', $result['month']);
        $this->assertSame('30', $result['year']);
        $this->assertSame('1.01', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('Auth', $result['transaction_type']);
        $this->assertSame(null, $result['auth_code']);
        $this->assertSame(null, $result['host_ref_num']);
        $this->assertSame(null, $result['status_detail']);
        $this->assertSame(null, $result['error_code']);
        $this->assertNotEmpty($result['all']);
    }

    private function get3DMakePaymentPaymentFailResponseData(): array
    {
        return [
            "OrderId" => "",
            "GroupId" => "",
            "Response" => "Error",
            "AuthCode" => "",
            "HostRefNum" => "",
            "ProcReturnCode" => "99",
            "TransId" => "22107QgTJ18637",
            "ErrMsg" => "Gecersiz Islem Tipi. Islem tipi Auth, PreAuth, PostAuth, Credit, Void islemlerinden biri olabilir.",
            "Extra" => [
                "SETTLEID" => "",
                "TRXDATE" => "20220417 16:32:19",
                "ERRORCODE" => "CORE-2001",
                "NUMCODE" => "992001",
            ],
        ];
    }

    private function get3DMakePaymentPaymentSuccessResponseData(): array
    {
        return [
            'OrderId' =>  '202204171C63',
            'GroupId' =>  '202204171C63',
            'Response' =>  'Approved',
            'AuthCode' =>  'P65781',
            'HostRefNum' =>  '210700616852',
            'ProcReturnCode' =>  '00',
            'TransId' =>  '22107PLcF14797',
            'ErrMsg' =>  '',
            'Extra' =>  [
                'SETTLEID' =>  '2092',
                'TRXDATE' =>  '20220417 15:11:28',
                'ERRORCODE' =>  '',
                'TERMINALID' =>  '00655020',
                'MERCHANTID' =>  '655000200',
                'CARDBRAND' =>  'VISA',
                'CARDISSUER' =>  'AKBANK T.A.S.',
                'AVSAPPROVE' =>  'Y',
                'HOSTDATE' =>  '0417-151128',
                'AVSERRORCODEDETAIL' =>  'avshatali-avshatali-avshatali-avshatali-',
                'NUMCODE' =>  '00',
            ],
        ];
    }

    private function get3DMakePaymentAuthSuccessResponseData(): array
    {
        return [
            'TRANID' => '',
            'PAResSyntaxOK' => 'true',
            'firmaadi' => 'John Doe',
            'lang' => 'tr',
            'merchantID' => '700655000200',
            'maskedCreditCard' => '4355 08** **** 4358',
            'amount' => '1.01',
            'sID' => '1',
            'ACQBIN' => '406456',
            'Ecom_Payment_Card_ExpDate_Year' => '30',
            'Email' => 'mail@customer.com',
            'MaskedPan' => '435508***4358',
            'clientIp' => '89.244.149.137',
            'iReqDetail' => '',
            'okUrl' => 'http://localhost/akbank/3d/response.php',
            'md' => '435508:46A6F89B64E81426ECA35D16C6E94AB6073E49F26ECE838705C267C58E894BB7:4232:##700655000200',
            'vendorCode' => '',
            'Ecom_Payment_Card_ExpDate_Month' => '12',
            'storetype' => '3d',
            'iReqCode' => '',
            'mdErrorMsg' => 'Authenticated',
            'PAResVerified' => 'false',
            'cavv' => 'AAABBDEjQgAAAAAwAiNCAAAAAAA=',
            'digest' => 'digest',
            'callbackCall' => 'true',
            'failUrl' => 'http://localhost/akbank/3d/response.php',
            'cavvAlgorithm' => '2',
            'xid' => '/ivt8mHWI9wJRQZQXelAt8m+Jj0=',
            'encoding' => 'ISO-8859-9',
            'currency' => '949',
            'oid' => '202204171C63',
            'mdStatus' => '1',
            'dsId' => '1',
            'eci' => '05',
            'version' => '2.0',
            'clientid' => '700655000200',
            'txstatus' => 'Y',
            '_charset_' => 'UTF-8',
            'HASH' => '7nVDw9NnL7z4KvTQB7fWhtN8ivQ=',
            'rnd' => '9vbiIiBpFoQTeE11rN+x',
            'HASHPARAMS' => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
            'HASHPARAMSVAL' => '700655000200202204171C631AAABBDEjQgAAAAAwAiNCAAAAAAA=05435508:46A6F89B64E81426ECA35D16C6E94AB6073E49F26ECE838705C267C58E894BB7:4232:##7006550002009vbiIiBpFoQTeE',
        ];
    }

    private function get3DMakePaymentFailResponseData(): array
    {
        return [
            'TRANID' => '',
            'PAResSyntaxOK' => 'true',
            'firmaadi' => 'John Doe',
            'lang' => 'tr',
            'merchantID' => '700655000200',
            'maskedCreditCard' => '4355 08** **** 4358',
            'amount' => '1.01',
            'sID' => '1',
            'ACQBIN' => '406456',
            'Ecom_Payment_Card_ExpDate_Year' => '30',
            'Email' => 'mail@customer.com',
            'MaskedPan' => '435508***4358',
            'clientIp' => '89.244.149.137',
            'iReqDetail' => '',
            'okUrl' => 'http://localhost/akbank/3d/response.php',
            'md' => '435508:86D9842A9C594E17B28A2B9037FEB140E8EA480AED5FE19B5CEA446960AA03AA:4122:##700655000200',
            'vendorCode' => '',
            'Ecom_Payment_Card_ExpDate_Month' => '12',
            'storetype' => '3d',
            'iReqCode' => '',
            'mdErrorMsg' => 'Not authenticated',
            'PAResVerified' => 'false',
            'cavv' => '',
            'digest' => 'digest',
            'callbackCall' => 'true',
            'failUrl' => 'http://localhost/akbank/3d/response.php',
            'cavvAlgorithm' => '',
            'xid' => 'FKqfXqwd0VA5RILtjmwaW17t/jk=',
            'encoding' => 'ISO-8859-9',
            'currency' => '949',
            'oid' => '202204171C44',
            'mdStatus' => '0',
            'dsId' => '1',
            'eci' => '',
            'version' => '2.0',
            'clientid' => '700655000200',
            'txstatus' => 'N',
            '_charset_' => 'UTF-8',
            'HASH' => 'e5KcIY797JNvjrkWjZSfHOa+690=',
            'rnd' => 'mzTLQAaM8W5GuQwu4BfD',
            'HASHPARAMS' => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
            'HASHPARAMSVAL' => '700655000200202204171C440435508:86D9842A9C594E17B28A2B9037FEB140E8EA480AED5FE19B5CEA446960AA03AA:4122:##700655000200mzTLQAaM8W5GuQwu4BfD',
        ];
    }

    private function get3DHostPaymentSuccessResponseData(): array
    {
        return [
            'panFirst6' => '',
            'TRANID' => '',
            'tadres2' => '',
            'SECMELIKAMPANYAKOD' => '000001',
            'PAResSyntaxOK' => 'true',
            'querydcchash' => '8YDETtZVvFBIdh4Vv5KvQ2oDiv1Xf7NIGpOFY9RlowWhBsCO8NT8vVHZ969XWRhrhdDZ3wYcwwjCaP/38npLBg==',
            'panLast4' => '',
            'firmaadi' => 'John Doe',
            'islemtipi' => 'Auth',
            'campaignOptions' => '000001',
            'refreshtime' => '300',
            'lang' => 'tr',
            'merchantID' => '700655000200',
            'maskedCreditCard' => '4355 08** **** 4358',
            'amount' => '1.01',
            'sID' => '1',
            'ACQBIN' => '406456',
            'Ecom_Payment_Card_ExpDate_Year' => '30',
            'MAXTIPLIMIT' => '0.00',
            'MaskedPan' => '435508***4358',
            'Email' => 'mail@customer.com',
            'Fadres' => '',
            'clientIp' => '89.244.149.137',
            'iReqDetail' => '',
            'girogateParamReqHash' => 'c1UjMNPnYeEp+z/pZOr8G0DAm3Ym+chx6T0PLR+Bmg/G0H8gmoISnoZAlehixmh6f5TTHpzFjE1Q+o0Rcekf2w==',
            'okUrl' => 'http://localhost/akbank/3d-host/response.php',
            'tismi' => '',
            'md' => '435508:D126F5C4AB8882BBCF51CDC7912CDE26DBAE8FBD8EA7FC94A9209D92478E22F3:4881:##700655000200',
            'vendorCode' => '',
            'Ecom_Payment_Card_ExpDate_Month' => '12',
            'tcknvkn' => '',
            'showdcchash' => 'dFo1wgY5yAnJU4Ops2b5WDO4CYC4/GHuRk0iEHFTo7VXzyy1DB1WRonnwpW78HSITxSNbkfGYQGLi4+yrzEHKQ==',
            'storetype' => '3d_host',
            'iReqCode' => '',
            'querycampainghash' => 'byYgCdEIadiqCcKjjDhRnPK38fe81wY8VHTmmmyfRJmA3FBwHExwlelxHynK4Hwdb99m/gqWoEXNIF6WhXhuvg==',
            'mdErrorMsg' => 'Authenticated',
            'PAResVerified' => 'false',
            'cavv' => 'AAABCCAykgAAAAAwAjKSAAAAAAA=',
            'digest' => 'digest',
            'callbackCall' => 'true',
            'failUrl' => 'http://localhost/akbank/3d-host/response.php',
            'cavvAlgorithm' => '2',
            'pbirimsembol' => 'TL ',
            'xid' => 'WXH3nXz6YmxmpO+Udhzxi3Zp/ZA=',
            'checkisonushash' => 'U70aRq7mQJlC6vm/tu3n9fMYKF3XHHHqOt9t/Z6B5e5E0fGOYFF7pKvHm7EMil+08OheTyGOG4CmIzfPjOcfbw==',
            'encoding' => 'ISO-8859-9',
            'currency' => '949',
            'oid' => '20220417FA2D',
            'mdStatus' => '1',
            'dsId' => '1',
            'eci' => '05',
            'version' => '2.0',
            'Fadres2' => '',
            'Fismi' => '',
            'clientid' => '700655000200',
            'txstatus' => 'Y',
            '_charset_' => 'UTF-8',
            'tadres' => '',
            'HASH' => 'bQY4zwZrjrlZmJWdRdDYgqCvMRU=',
            'rnd' => 'iPz5dJrRadaSXVCyTtHC',
            'HASHPARAMS' => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
            'HASHPARAMSVAL' => '70065500020020220417FA2D1AAABCCAykgAAAAAwAjKSAAAAAAA=05435508:D126F5C4AB8882BBCF51CDC7912CDE26DBAE8FBD8EA7FC94A9209D92478E22F3:4881:##700655000200iPz5dJrRadaSXVCyTtHC',
        ];
    }

    private function get3DHostPaymentFailResponseData(): array
    {
        return [
            'panFirst6' => '',
            'TRANID' => '',
            'tadres2' => '',
            'SECMELIKAMPANYAKOD' => '000001',
            'PAResSyntaxOK' => 'true',
            'querydcchash' => '/megIKrKuwMtqh4GbkbX3z6GoSaUYD2vA7nIo+KqXLRyF1gm/Z9Ys/FqcFFLkzC1qzJKv4KjB8jIm7aX5LYRIw==',
            'panLast4' => '',
            'firmaadi' => 'John Doe',
            'islemtipi' => 'Auth',
            'campaignOptions' => '000001',
            'refreshtime' => '300',
            'lang' => 'tr',
            'merchantID' => '700655000200',
            'maskedCreditCard' => '4355 08** **** 4358',
            'amount' => '1.01',
            'sID' => '1',
            'ACQBIN' => '406456',
            'Ecom_Payment_Card_ExpDate_Year' => '30',
            'MAXTIPLIMIT' => '0.00',
            'MaskedPan' => '435508***4358',
            'Email' => 'mail@customer.com',
            'Fadres' => '',
            'clientIp' => '89.244.149.137',
            'iReqDetail' => '',
            'girogateParamReqHash' => 'AMfID/G6bdwHDSXYley9G8t+5ne/4Ar+Yh3Y2mIrFEI6hMhxKQUdB0ene535crf+TeQFTpw9vWYavvGzEyARqQ==',
            'okUrl' => 'http://localhost/akbank/3d-host/response.php',
            'tismi' => '',
            'md' => '435508:524D8E0D689F6F5E1DD0C737ED160B6073038B4FBBC73E6D7C69341793A2DC0E:3379:##700655000200',
            'vendorCode' => '',
            'Ecom_Payment_Card_ExpDate_Month' => '12',
            'tcknvkn' => '',
            'showdcchash' => 'IP0lsEyyePWlDTaE2DIiO+oUrfky1R7sENOM3vTsgluXaFeYT3oCc01y/nNW8JhsJyNSuGG7Oyc1lyX7mYu2Qw==',
            'storetype' => '3d_host',
            'iReqCode' => '',
            'querycampainghash' => 'gVSZ/WicO5xjnoBx5uVR2iutwo/6J9j35WQ/+ZJl8CJfLJ05fZOOumfwi3T6LAw3EMGSd9Ui1JN4q06s1qNNtA==',
            'mdErrorMsg' => 'Not authenticated',
            'PAResVerified' => 'false',
            'cavv' => '',
            'digest' => 'digest',
            'callbackCall' => 'true',
            'failUrl' => 'http://localhost/akbank/3d-host/response.php',
            'cavvAlgorithm' => '',
            'pbirimsembol' => 'TL ',
            'xid' => 'waMCr/n5dMiGv2+cQoEfCbe6h/A=',
            'checkisonushash' => 'iHqkebVVeQLrrMzlNWxs8819FOMOeSqlVFwiMMKVV70uXYIAFf5Zz+jw/s4wJ4VtjZo34dxSzUCaThfTht6tQA==',
            'encoding' => 'ISO-8859-9',
            'currency' => '949',
            'oid' => '202204175A83',
            'mdStatus' => '0',
            'dsId' => '1',
            'eci' => '',
            'version' => '2.0',
            'Fadres2' => '',
            'Fismi' => '',
            'clientid' => '700655000200',
            'txstatus' => 'N',
            '_charset_' => 'UTF-8',
            'tadres' => '',
            'HASH' => 'RnwcttwleDHhbpVDD2ZszfFJhRA=',
            'rnd' => 'g+XYZKbjrxFj5EgZNZFj',
            'HASHPARAMS' => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
            'HASHPARAMSVAL' => '700655000200202204175A830435508:524D8E0D689F6F5E1DD0C737ED160B6073038B4FBBC73E6D7C69341793A2DC0E:3379:##700655000200g+XYZKbjrxFj5EgZNZFj',
        ];
    }


    /**
     * @param                  $order
     * @param CreditCardEstPos $card
     * @param EstPosAccount    $account
     *
     * @return array
     */
    private function getSampleRegularPaymentXMLData($order, CreditCardEstPos $card, EstPosAccount $account)
    {
        return [
            'Name'      => $account->getUsername(),
            'Password'  => $account->getPassword(),
            'ClientId'  => $account->getClientId(),
            'Type'      => 'Auth',
            'IPAddress' => $order->ip,
            'Email'     => $order->email,
            'OrderId'   => $order->id,
            'UserId'    => isset($order->user_id) ? $order->user_id : null,
            'Total'     => $order->amount,
            'Currency'  => $order->currency,
            'Taksit'    => $order->installment,
            'CardType'  => $card->getType(),
            'Number'    => $card->getNumber(),
            'Expires'   => $card->getExpirationDate(),
            'Cvv2Val'   => $card->getCvv(),
            'Mode'      => 'P',
            'GroupId'   => '',
            'TransId'   => '',
            'BillTo'    => [
                'Name' => $order->name ? $order->name : null,
            ],
        ];
    }

    /**
     * @param               $order
     * @param EstPosAccount $account
     *
     * @return array
     */
    private function getSampleRegularPostXMLData($order, $account)
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'Type'     => 'PostAuth',
            'OrderId'  => $order->id,
        ];
    }

    /**
     * @param               $order
     * @param EstPosAccount $account
     * @param array         $responseData
     *
     * @return array
     */
    private function getSample3DPaymentXMLData($order, $account, array $responseData)
    {
        $requestData = [
            'Name'                    => $account->getUsername(),
            'Password'                => $account->getPassword(),
            'ClientId'                => $account->getClientId(),
            'Type'                    => 'Auth',
            'IPAddress'               => $order->ip,
            'Email'                   => $order->email,
            'OrderId'                 => $order->id,
            'UserId'                  => isset($order->user_id) ? $order->user_id : null,
            'Total'                   => $order->amount,
            'Currency'                => $order->currency,
            'Taksit'                  => $order->installment,
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
        if (isset($order->name)) {
            $requestData['BillTo'] = [
                'Name' => $order->name,
            ];
        }

        if (isset($order->recurringFrequency)) {
            $requestData['PbOrder'] = [
                'OrderType'              => 0,
                'OrderFrequencyInterval' => $order->recurringFrequency,
                'OrderFrequencyCycle'    => $order->recurringFrequencyType,
                'TotalNumberPayments'    => $order->recurringInstallmentCount,
            ];
        }

        return $requestData;
    }

    /**
     * @param               $order
     * @param EstPosAccount $account
     *
     * @return array
     */
    private function getSampleStatusXMLData($order, $account)
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order->id,
            'Extra'    => [
                'ORDERSTATUS' => 'QUERY',
            ],
        ];
    }

    /**
     * @param               $order
     * @param EstPosAccount $account
     *
     * @return array
     */
    private function getSampleCancelXMLData($order, $account)
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order->id,
            'Type'     => 'Void',
        ];
    }

    /**
     * @param               $order
     * @param EstPosAccount $account
     *
     * @return array
     */
    private function getSampleRefundXMLData($order, $account)
    {
        $data = [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order->id,
            'Type'     => 'Credit',
        ];

        if ($order->amount) {
            $data['Total'] = $order->amount;
        }

        return $data;
    }


    /**
     * @param string $name
     *
     * @return ReflectionMethod
     *
     * @throws ReflectionException
     */
    private static function getProtectedMethod(string $name)
    {
        $class = new ReflectionClass(EstPos::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}

<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\EstPos;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * EstPosTest
 */
class EstPosTest extends TestCase
{
    /** @var EstPosAccount */
    private $account;
    /** @var EstPos */
    private $pos;
    private $config;

    /** @var AbstractCreditCard */
    private $card;
    private $order;

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
            AbstractGateway::LANG_TR
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
        $data = $this->get3DMakePaymentFailResponseData();
        $this->assertTrue($this->pos->check3DHash($data));

        $data['mdStatus'] = '';
        $this->assertFalse($this->pos->check3DHash($data));
    }

    /**
     * @return void
     */
    public function testMake3DPaymentAuthFail()
    {
        $request = Request::create('', 'POST', $this->get3DMakePaymentFailResponseData());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(EstPos::class)])
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
        $this->assertSame('Auth', $result['transaction']);
        $this->assertSame(AbstractGateway::TX_PAY, $result['transaction_type']);
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
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(EstPos::class)])
            ->onlyMethods(['send', 'check3DHash', 'create3DPaymentXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn((object) $this->get3DMakePaymentPaymentFailResponseData());
        $posMock->expects($this->once())->method('check3DHash')->willReturn(true);
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
        $this->assertSame('Auth', $result['transaction']);
        $this->assertSame(AbstractGateway::TX_PAY, $result['transaction_type']);
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
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(EstPos::class)])
            ->onlyMethods(['send', 'check3DHash', 'create3DPaymentXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn((object) $this->get3DMakePaymentPaymentSuccessResponseData());
        $posMock->expects($this->once())->method('check3DHash')->willReturn(true);
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
        $this->assertSame('Auth', $result['transaction']);
        $this->assertSame(AbstractGateway::TX_PAY, $result['transaction_type']);
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
        $this->assertSame('Auth', $result['transaction']);
        $this->assertSame(AbstractGateway::TX_PAY, $result['transaction_type']);
        $this->assertSame(null, $result['auth_code']);
        $this->assertSame(null, $result['host_ref_num']);
        $this->assertSame(null, $result['status_detail']);
        $this->assertSame(null, $result['error_code']);
        $this->assertNotEmpty($result['all']);
    }

    /**
     * @return void
     */
    public function testMake3DHostPaymentFail()
    {
        $request = Request::create('', 'POST', $this->get3DHostPaymentFailResponseData());

        $pos = $this->pos;
        $pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $pos->make3DHostPayment($request);
        $result = $pos->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('declined', $result['status']);
        $this->assertSame('Not authenticated', $result['md_error_message']);
        $this->assertSame('202204175A83', $result['order_id']);
        $this->assertSame('0', $result['md_status']);
        $this->assertSame('RnwcttwleDHhbpVDD2ZszfFJhRA=', $result['hash']);
        $this->assertSame('4355 08** **** 4358', $result['masked_number']);
        $this->assertSame('12', $result['month']);
        $this->assertSame('30', $result['year']);
        $this->assertSame('1.01', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('Auth', $result['transaction']);
        $this->assertSame(AbstractGateway::TX_PAY, $result['transaction_type']);
        $this->assertSame(null, $result['auth_code']);
        $this->assertSame(null, $result['host_ref_num']);
        $this->assertSame(null, $result['status_detail']);
        $this->assertSame(null, $result['error_code']);
        $this->assertNotEmpty($result['all']);
    }

    /**
     * @return void
     */
    public function testMake3DPayPaymentSuccess()
    {
        $request = Request::create('', 'POST', $this->get3DPayPaymentSuccessResponseData());

        $pos = $this->pos;
        $pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $pos->make3DHostPayment($request);
        $result = $pos->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('approved', $result['status']);
        $this->assertSame(null, $result['md_error_message']);
        $this->assertSame('202205220386', $result['order_id']);
        $this->assertSame('1', $result['md_status']);
        $this->assertSame('GlAHT733ITdOZ1Lj5OJGvzAJlt8=', $result['hash']);
        $this->assertSame('4355 08** **** 4358', $result['masked_number']);
        $this->assertSame('12', $result['month']);
        $this->assertSame('30', $result['year']);
        $this->assertSame('1.01', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('Auth', $result['transaction']);
        $this->assertSame(AbstractGateway::TX_PAY, $result['transaction_type']);
        $this->assertSame(null, $result['auth_code']);
        $this->assertSame(null, $result['host_ref_num']);
        $this->assertSame(null, $result['status_detail']);
        $this->assertSame(null, $result['error_code']);
        $this->assertNotEmpty($result['all']);
    }

    /**
     * @return void
     */
    public function testMake3DPayPayment3DAuthFail()
    {
        $request = Request::create('', 'POST', $this->get3DPayPaymentAuthFailResponseData());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(EstPos::class)])
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
        $this->assertSame('202205222012', $result['order_id']);
        $this->assertSame('0', $result['md_status']);
        $this->assertSame('G1OJfCYcAbbrFHjF16heHTcP2Co=', $result['hash']);
        $this->assertSame('4355 08** **** 4358', $result['masked_number']);
        $this->assertSame('12', $result['month']);
        $this->assertSame('30', $result['year']);
        $this->assertSame('1.01', $result['amount']);
        $this->assertSame('TRY', $result['currency']);
        $this->assertSame('Auth', $result['transaction']);
        $this->assertSame(AbstractGateway::TX_PAY, $result['transaction_type']);
        $this->assertSame(null, $result['auth_code']);
        $this->assertSame(null, $result['host_ref_num']);
        $this->assertSame(null, $result['status_detail']);
        $this->assertSame(null, $result['error_code']);
        $this->assertNotEmpty($result['3d_all']);
    }

    /**
     * @return void
     */
    public function testStatusSuccess()
    {
        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(EstPos::class)])
            ->onlyMethods(['send', 'createStatusXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn((object) $this->getStatusSuccessResponseData());
        $posMock->expects($this->once())->method('createStatusXML')->willReturn('');

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $posMock->status();
        $result = $posMock->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('approved', $result['status']);
        $this->assertSame('20220417B473', $result['order_id']);
        $this->assertSame('4355 08** **** 4358', $result['masked_number']);
        $this->assertSame('101', $result['capture_amount']);
        $this->assertSame('101', $result['first_amount']);
        $this->assertSame('P58683', $result['auth_code']);
        $this->assertSame('210700616873', $result['host_ref_num']);
        $this->assertSame('approved', $result['status_detail']);
        $this->assertSame(null, $result['error_code']);
        $this->assertSame(true, $result['capture']);
        $this->assertNotEmpty($result['all']);
    }

    /**
     * @return void
     */
    public function testStatusFail()
    {
        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(EstPos::class)])
            ->onlyMethods(['send', 'createStatusXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn((object) $this->getStatusFailResponseData());
        $posMock->expects($this->once())->method('createStatusXML')->willReturn('');

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $posMock->status();
        $result = $posMock->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('declined', $result['status']);
        $this->assertSame(null, $result['order_id']);
        $this->assertSame(null, $result['masked_number']);
        $this->assertSame(null, $result['capture_amount']);
        $this->assertSame(null, $result['first_amount']);
        $this->assertSame(null, $result['auth_code']);
        $this->assertSame(null, $result['host_ref_num']);
        $this->assertSame('general_error', $result['status_detail']);
        $this->assertSame(null, $result['error_code']);
        $this->assertSame(false, $result['capture']);
        $this->assertNotEmpty($result['all']);
    }

    /**
     * @return void
     */
    public function testHistorySuccess()
    {
        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(EstPos::class)])
            ->onlyMethods(['send', 'createHistoryXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn((object) $this->getHistorySuccessData());
        $posMock->expects($this->once())->method('createHistoryXML')->willReturn('');

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $posMock->history([]);
        $result = $posMock->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('approved', $result['status']);
        $this->assertSame('20220417B473', $result['order_id']);
        $this->assertSame('approved', $result['status_detail']);
        $this->assertSame('00', $result['proc_return_code']);
        $this->assertSame(null, $result['error_message']);
        $this->assertSame('0', $result['num_code']);
        $this->assertSame('1', $result['trans_count']);
        $this->assertSame('Approved', $result['response']);
        $this->assertNotEmpty($result['all']);
    }

    /**
     * @return void
     */
    public function testHistoryFail()
    {
        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(EstPos::class)])
            ->onlyMethods(['send', 'createHistoryXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn((object) $this->getHistoryFailData());
        $posMock->expects($this->once())->method('createHistoryXML')->willReturn('');

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $posMock->history([]);
        $result = $posMock->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('declined', $result['status']);
        $this->assertSame(null, $result['order_id']);
        $this->assertSame('reject', $result['status_detail']);
        $this->assertSame('05', $result['proc_return_code']);
        $this->assertSame('No record found for', $result['error_message']);
        $this->assertSame('0', $result['num_code']);
        $this->assertSame('0', $result['trans_count']);
        $this->assertSame('Declined', $result['response']);
        $this->assertNotEmpty($result['all']);
    }

    /**
     * @return void
     */
    public function testCancelSuccess()
    {
        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(EstPos::class)])
            ->onlyMethods(['send', 'createCancelXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn((object) $this->getCancelSuccessData());
        $posMock->expects($this->once())->method('createCancelXML')->willReturn('');

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $posMock->cancel();
        $result = $posMock->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('approved', $result['status']);
        $this->assertSame('P58683', $result['auth_code']);
        $this->assertSame('20220417B473', $result['group_id']);
        $this->assertSame('20220417B473', $result['order_id']);
        $this->assertSame('approved', $result['status_detail']);
        $this->assertSame('00', $result['proc_return_code']);
        $this->assertSame(null, $result['error_message']);
        $this->assertSame(null, $result['error_code']);
        $this->assertSame('00', $result['num_code']);
        $this->assertSame('22107TcKA17186', $result['trans_id']);
        $this->assertSame('Approved', $result['response']);
        $this->assertNotEmpty($result['all']);
    }

    /**
     * @return void
     */
    public function testCancelFail()
    {
        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(EstPos::class)])
            ->onlyMethods(['send', 'createCancelXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn((object) $this->getCancelFailData());
        $posMock->expects($this->once())->method('createCancelXML')->willReturn('');

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $posMock->cancel();
        $result = $posMock->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('declined', $result['status']);
        $this->assertSame(null, $result['auth_code']);
        $this->assertSame(null, $result['group_id']);
        $this->assertSame(null, $result['order_id']);
        $this->assertSame('general_error', $result['status_detail']);
        $this->assertSame('99', $result['proc_return_code']);
        $this->assertSame('İptal edilmeye uygun satış işlemi bulunamadı.', $result['error_message']);
        $this->assertSame('CORE-2008', $result['error_code']);
        $this->assertSame('992008', $result['num_code']);
        $this->assertSame('22107VpnG13127', $result['trans_id']);
        $this->assertSame('Error', $result['response']);
        $this->assertNotEmpty($result['all']);
    }

    /**
     * @return void
     */
    public function testRefundFail()
    {
        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(EstPos::class)])
            ->onlyMethods(['send', 'createRefundXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn((object) $this->getRefundFailData());
        $posMock->expects($this->once())->method('createRefundXML')->willReturn('');

        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $posMock->refund();
        $result = $posMock->getResponse();
        $this->assertIsObject($result);
        $result = (array) $result;
        $this->assertSame('declined', $result['status']);
        $this->assertSame(null, $result['auth_code']);
        $this->assertSame('46546551651', $result['group_id']);
        $this->assertSame('46546551651', $result['order_id']);
        $this->assertSame('general_error', $result['status_detail']);
        $this->assertSame('99', $result['proc_return_code']);
        $this->assertSame('Iade icin eslesen siparis bulunamadi.', $result['error_message']);
        $this->assertSame('CORE-2123', $result['error_code']);
        $this->assertSame('992123', $result['num_code']);
        $this->assertSame('22107WC9G14071', $result['trans_id']);
        $this->assertSame('Error', $result['response']);
        $this->assertNotEmpty($result['all']);
    }

    /**
     * @return array
     */
    private function getRefundFailData(): array
    {
        return [
            'OrderId' => '46546551651',
            'GroupId' => '46546551651',
            'Response' => 'Error',
            'AuthCode' => '',
            'HostRefNum' => '',
            'ProcReturnCode' => '99',
            'TransId' => '22107WC9G14071',
            'ErrMsg' => 'Iade icin eslesen siparis bulunamadi.',
            'Extra' => [
                'SETTLEID' => '',
                'TRXDATE' => '20220417 22:02:59',
                'ERRORCODE' => 'CORE-2123',
                'NUMCODE' => '992123',
            ],
        ];
    }

    /**
     * @return array
     */
    private function getCancelSuccessData(): array
    {
        return [
            'OrderId'        => '20220417B473',
            'GroupId'        => '20220417B473',
            'Response'       => 'Approved',
            'AuthCode'       => 'P58683',
            'HostRefNum'     => '210700616873',
            'ProcReturnCode' => '00',
            'TransId'        => '22107TcKA17186',
            'ErrMsg'         => '',
            'Extra'          => (object) [
                'SETTLEID'   => '2092',
                'TRXDATE'    => '20220417 19:28:09',
                'ERRORCODE'  => '',
                'TERMINALID' => '00655020',
                'MERCHANTID' => '655000200',
                'CARDBRAND'  => 'VISA',
                'CARDISSUER' => 'AKBANK T.A.S.',
                'HOSTDATE'   => '0417-213341',
                'NUMCODE'    => '00',
            ],
        ];
    }

    /**
     * @return array
     */
    private function getCancelFailData(): array
    {
        return [
            'OrderId'        => '',
            'GroupId'        => '',
            'Response'       => 'Error',
            'AuthCode'       => '',
            'HostRefNum'     => '',
            'ProcReturnCode' => '99',
            'TransId'        => '22107VpnG13127',
            'ErrMsg'         => 'İptal edilmeye uygun satış işlemi bulunamadı.',
            'Extra'          => [
                'SETTLEID'  => '',
                'TRXDATE'   => '20220417 21:41:39',
                'ERRORCODE' => 'CORE-2008',
                'NUMCODE'   => '992008',
            ],
        ];
    }

    /**
     * @return array
     */
    private function getHistoryFailData(): array
    {
        return [
            'ErrMsg'         => 'No record found for',
            'ProcReturnCode' => '05',
            'Response'       => 'Declined',
            'OrderId'        => '',
            'Extra'          => [
                'NUMCODE'  => '0',
                'TRXCOUNT' => '0',
            ],
        ];
    }

    /**
     * @return array
     */
    private function getHistorySuccessData(): array
    {
        return [
            'ErrMsg' => '',
            'ProcReturnCode' => '00',
            'Response' => 'Approved',
            'OrderId' => '20220417B473',
            'Extra' => (object) [
                'TERMINALID' => '00655020',
                'MERCHANTID' => '655000200',
                'NUMCODE' => '0',
                'TRX1' => 'S\tC\t101\t101\t2022-04-17 19:28:09.777\t2022-04-17 19:28:09.777\t\t210700616873\tP58683\t00\t22107TcKA17186',
                'TRXCOUNT' => '1',
            ],
        ];
    }

    /**
     * @return array
     */
    private function get3DMakePaymentPaymentFailResponseData(): array
    {
        return [
            'OrderId' => '',
            'GroupId' => '',
            'Response' => 'Error',
            'AuthCode' => '',
            'HostRefNum' => '',
            'ProcReturnCode' => '99',
            'TransId' => '22107QgTJ18637',
            'ErrMsg' => 'Gecersiz Islem Tipi. Islem tipi Auth, PreAuth, PostAuth, Credit, Void islemlerinden biri olabilir.',
            'Extra' => [
                'SETTLEID' => '',
                'TRXDATE' => '20220417 16:32:19',
                'ERRORCODE' => 'CORE-2001',
                'NUMCODE' => '992001',
            ],
        ];
    }

    /**
     * @return array
     */
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

    /**
     * @return string[]
     */
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

    /**
     * @return string[]
     */
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

    /**
     * @return string[]
     */
    private function get3DPayPaymentAuthFailResponseData(): array
    {
        return [
            'TRANID' => null,
            'PAResSyntaxOK' => 'true',
            'firmaadi' => 'John Doe',
            'islemtipi' => 'Auth',
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
            'iReqDetail' => null,
            'okUrl' => 'http://localhost/akbank/3d-pay/response.php',
            'md' => '435508:7F00F303E25EEA46F866AD14BD19D4F408C26E0FD6797C06ED0E334B61E320A1:3896:##700655000200',
            'taksit' => null,
            'vendorCode' => null,
            'Ecom_Payment_Card_ExpDate_Month' => '12',
            'storetype' => '3d_pay',
            'iReqCode' => null,
            'mdErrorMsg' => 'Not authenticated',
            'PAResVerified' => 'false',
            'cavv' => null,
            'digest' => 'digest',
            'callbackCall' => 'true',
            'failUrl' => 'http://localhost/akbank/3d-pay/response.php',
            'cavvAlgorithm' => null,
            'xid' => '5hJlJeQBU6rnINPa4AZXiBbHC8s=',
            'encoding' => 'ISO-8859-9',
            'currency' => '949',
            'oid' => '202205222012',
            'mdStatus' => '0',
            'dsId' => '1',
            'eci' => null,
            'version' => '2.0',
            'clientid' => '700655000200',
            'txstatus' => 'N',
            '_charset_' => 'UTF-8',
            'HASH' => 'G1OJfCYcAbbrFHjF16heHTcP2Co=',
            'rnd' => '31CSYOlpJylTS4lGM5X5',
            'HASHPARAMS' => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
            'HASHPARAMSVAL' => '7006550002002022052220120435508:7F00F303E25EEA46F866AD14BD19D4F408C26E0FD6797C06ED0E334B61E320A1:3896:##70065500020031CSYOlpJylTS4lGM5X5'
        ];
    }

    /**
     * @return string[]
     */
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

    /**
     * @return string[]
     */
    private function get3DPayPaymentSuccessResponseData(): array
    {
        return [
            'ReturnOid' => '202205220386',
            'TRANID' => '',
            'EXTRA_MERCHANTID' => '655000200',
            'PAResSyntaxOK' => 'true',
            'EXTRA_HOSTDATE' => '0522-104635',
            'firmaadi' => 'John Doe',
            'islemtipi' => 'Auth',
            'EXTRA_TERMINALID' => '00655020',
            'lang' => 'tr',
            'merchantID' => '700655000200',
            'maskedCreditCard' => '4355 08** **** 4358',
            'amount' => '1.01',
            'sID' => '1',
            'ACQBIN' => '406456',
            'Ecom_Payment_Card_ExpDate_Year' => '30',
            'EXTRA_CARDBRAND' => 'VISA',
            'Email' => 'mail@customer.com',
            'MaskedPan' => '435508***4358',
            'acqStan' => '626247',
            'clientIp' => '89.244.149.137',
            'iReqDetail' => '',
            'okUrl' => 'http://localhost/akbank/3d-pay/response.php',
            'md' => '435508:09924180A54400523140B28560F171351C59BF7A937A6DE785D90CDF9CCD2153:3691:##700655000200',
            'ProcReturnCode' => '00',
            'payResults_dsId' => '1',
            'taksit' => '2',
            'vendorCode' => '',
            'TransId' => '22142KugH13407',
            'EXTRA_TRXDATE' => '20220522 10:46:31',
            'Ecom_Payment_Card_ExpDate_Month' => '12',
            'storetype' => '3d_pay',
            'iReqCode' => '',
            'Response' => 'Approved',
            'SettleId' => '2127',
            'mdErrorMsg' => 'Authenticated',
            'ErrMsg' => '',
            'PAResVerified' => 'false',
            'cavv' => 'AAABBVApAgAAAAAwYCkCAAAAAAA=',
            'digest' => 'digest',
            'HostRefNum' => '214200626247',
            'callbackCall' => 'true',
            'AuthCode' => 'T96294',
            'failUrl' => 'http://localhost/akbank/3d-pay/response.php',
            'cavvAlgorithm' => '2',
            'xid' => 'dC5wDSc3ayPDPeKHkN2SamHbTn4=',
            'encoding' => 'ISO-8859-9',
            'currency' => '949',
            'oid' => '202205220386',
            'mdStatus' => '1',
            'dsId' => '1',
            'eci' => '05',
            'version' => '2.0',
            'EXTRA_CARDISSUER' => 'AKBANK T.A.S.',
            'clientid' => '700655000200',
            'txstatus' => 'Y',
            '_charset_' => 'UTF-8',
            'HASH' => 'GlAHT733ITdOZ1Lj5OJGvzAJlt8=',
            'rnd' => 'mEHdv+pi0OV0uDw7MrEB',
            'HASHPARAMS' => 'clientid:oid:AuthCode:ProcReturnCode:Response:mdStatus:cavv:eci:md:rnd:',
            'HASHPARAMSVAL' => '700655000200202205220386T9629400Approved1AAABBVApAgAAAAAwYCkCAAAAAAA=05435508:09924180A54400523140B28560F171351C59BF7A937A6DE785D90CDF9CCD2153:3691:##700655000200mEHdv+pi0OV0uDw7MrEB',
        ];
    }

    /**
     * @return array
     */
    private function getStatusSuccessResponseData(): array
    {
        return [
            'ErrMsg' => 'Record(s) found for 20220417B473',
            'ProcReturnCode' => '00',
            'Response' => 'Approved',
            'OrderId' => '20220417B473',
            'TransId' => '22107TcKA17186',
            'Extra' => (object) [
                'AUTH_CODE' => 'P58683',
                'AUTH_DTTM' => '2022-04-17 19:28:09.777',
                'CAPTURE_AMT' => '101',
                'CAPTURE_DTTM' => '2022-04-17 19:28:09.777',
                'CAVV_3D' => '',
                'CHARGE_TYPE_CD' => 'S',
                'ECI_3D' => '',
                'HOSTDATE' => '0417-192810',
                'HOST_REF_NUM' => '210700616873',
                'MDSTATUS' => '',
                'NUMCODE' => '0',
                'ORDERSTATUS' => 'ORD_ID:20220417B473\tCHARGE_TYPE_CD:S\tORIG_TRANS_AMT:101\tCAPTURE_AMT:101\tTRANS_STAT:C\tAUTH_DTTM:2022-04-17 19:28:09.777\tCAPTURE_DTTM:2022-04-17 19:28:09.777\tAUTH_CODE:P58683\tTRANS_ID:22107TcKA17186',
                'ORD_ID' => '20220417B473',
                'ORIG_TRANS_AMT' => '101',
                'PAN' => '4355 08** **** 4358',
                'PROC_RET_CD' => '00',
                'SETTLEID' => '',
                'TRANS_ID' => '22107TcKA17186',
                'TRANS_STAT' => 'C',
                'XID_3D' => '',
            ],
        ];
    }

    /**
     * @return array
     */
    private function getStatusFailResponseData(): array
    {
        return [
            'ErrMsg' => 'No record found for order222',
            'ProcReturnCode' => '99',
            'Response' => 'Declined',
            'OrderId' => '',
            'TransId' => '',
            'Extra' => [
                'NUMCODE' => '0',
                'ORDERSTATUS' => 'ORD_ID:\tCHARGE_TYPE_CD:\tORIG_TRANS_AMT:\tCAPTURE_AMT:\tTRANS_STAT:\tAUTH_DTTM:\tCAPTURE_DTTM:\tAUTH_CODE:',
            ],
        ];
    }

    /**
     * @return string[]
     */
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
}

<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\DataMapper\ResponseDataMapper\EstPosResponseDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $this->account = AccountFactory::createEstPosAccount(
            'akbank',
            '700655000200',
            'ISBANKAPI',
            'ISBANK07',
            PosInterface::MODEL_3D_SECURE,
            'TRPS0200'
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

        $this->pos             = PosFactory::createPosGateway($this->account, $this->config);
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
    public function testMake3DPaymentAuthFail()
    {
        $request = Request::create('', 'POST', EstPosResponseDataMapperTest::threeDPayPaymentDataProvider()['authFail1']['paymentData']);

        $crypt = PosFactory::getGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(EstPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->expects($this->never())->method('send');

        $posMock->make3DPayment($request, $this->order, PosInterface::TX_PAY, $this->card);

        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertFalse($posMock->isSuccess());
    }

    /**
     * @return void
     */
    public function testMake3DHostPaymentSuccess()
    {
        $request = Request::create('', 'POST', EstPosResponseDataMapperTest::threeDHostPaymentDataProvider()['success1']['paymentData']);

        $pos = $this->pos;

        $pos->make3DHostPayment($request);

        $result = $pos->getResponse();
        $this->assertIsArray($result);
        $this->assertTrue($pos->isSuccess());
    }

    /**
     * @return void
     */
    public function testMake3DPayPaymentSuccess()
    {
        $request = Request::create('', 'POST', EstPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData']);

        $pos = $this->pos;

        $pos->make3DPayPayment($request);

        $result = $pos->getResponse();
        $this->assertIsArray($result);
        $this->assertTrue($pos->isSuccess());
    }

    /**
     * @return void
     */
    public function testMake3DPayPayment3DAuthFail()
    {
        $request = Request::create('', 'POST', EstPosResponseDataMapperTest::threeDPayPaymentDataProvider()['authFail1']['paymentData']);

        $crypt = PosFactory::getGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(EstPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->expects($this->never())->method('send');

        $posMock->make3DPayment($request, $this->order, PosInterface::TX_PAY, $this->card);

        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertFalse($posMock->isSuccess());
    }

    /**
     * @dataProvider statusDataProvider
     */
    public function testStatus(array $testData, bool $isSuccess): void
    {
        $crypt = PosFactory::getGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(EstPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['send', 'createStatusXML', 'getQueryAPIUrl'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn($testData);
        $posMock->expects($this->once())->method('createStatusXML')->willReturn('');
        $posMock->expects($this->once())->method('getQueryAPIUrl')->willReturn('');

        $posMock->status($this->order);

        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertSame($isSuccess, $posMock->isSuccess());
    }

    /**
     * @return void
     */
    public function testHistorySuccess()
    {
        $crypt = PosFactory::getGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(EstPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['send', 'createHistoryXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn(
            EstPosResponseDataMapperTest::historyTestDataProvider()['success1']['responseData']
        );
        $posMock->expects($this->once())->method('createHistoryXML')->willReturn('');

        $posMock->history($this->order);

        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertTrue($posMock->isSuccess());
    }

    /**
     * @return void
     */
    public function testHistoryFail()
    {
        $crypt = PosFactory::getGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(EstPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['send', 'createHistoryXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn(
            EstPosResponseDataMapperTest::historyTestDataProvider()['fail1']['responseData']
        );
        $posMock->expects($this->once())->method('createHistoryXML')->willReturn('');

        $posMock->history($this->order);

        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertFalse($posMock->isSuccess());
    }

    /**
     * @return void
     */
    public function testCancelSuccess()
    {
        $crypt = PosFactory::getGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(EstPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['send', 'createCancelXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn(
            EstPosResponseDataMapperTest::cancelTestDataProvider()['success1']['responseData']
        );
        $posMock->expects($this->once())->method('createCancelXML')->willReturn('');

        $posMock->cancel($this->order);

        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertTrue($posMock->isSuccess());
    }

    /**
     * @return void
     */
    public function testCancelFail()
    {
        $crypt = PosFactory::getGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(EstPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['send', 'createCancelXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn(
            EstPosResponseDataMapperTest::cancelTestDataProvider()['fail1']['responseData']
        );
        $posMock->expects($this->once())->method('createCancelXML')->willReturn('');

        $posMock->cancel($this->order);

        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertFalse($posMock->isSuccess());
    }

    /**
     * @return void
     */
    public function testRefundFail()
    {
        $crypt = PosFactory::getGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(EstPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['send', 'createRefundXML'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn(
            EstPosResponseDataMapperTest::refundTestDataProvider()['fail1']['responseData']
        );
        $posMock->expects($this->once())->method('createRefundXML')->willReturn('');

        $posMock->refund($this->order);

        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertFalse($posMock->isSuccess());
    }

    public static function statusDataProvider(): iterable
    {
        yield [
            'responseData' => EstPosResponseDataMapperTest::statusTestDataProvider()['fail1']['responseData'],
            'isSuccess' => false,
        ];
        yield [
            'responseData' => EstPosResponseDataMapperTest::statusTestDataProvider()['success1']['responseData'],
            'isSuccess' => true,
        ];
    }

}

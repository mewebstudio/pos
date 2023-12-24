<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\EstPosResponseDataMapper;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Factory\RequestDataMapperFactory;
use Mews\Pos\Factory\ResponseDataMapperFactory;
use Mews\Pos\Factory\SerializerFactory;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\EstPosResponseDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\EstPos
 */
class EstPosTest extends TestCase
{
    private EstPosAccount $account;

    /** @var EstPos */
    private PosInterface $pos;

    /** @var array<string, mixed> */
    private $config;

    private CreditCardInterface $card;

    private array $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../../config/pos_test.php';

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
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];

        $this->pos = PosFactory::createPosGateway($this->account, $this->config, $this->createMock(EventDispatcherInterface::class));
        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '21', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
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
        $testData = EstPosResponseDataMapperTest::threeDPayPaymentDataProvider()['authFail1'];
        $request  = Request::create('', 'POST', $testData['paymentData']);
        $order    = $testData['order'];
        $txType   = $testData['txType'];

        $crypt          = CryptFactory::createGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper  = RequestDataMapperFactory::createGatewayRequestMapper(EstPos::class, $this->createMock(EventDispatcherInterface::class), $crypt);
        $responseMapper = ResponseDataMapperFactory::createGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());
        $serializer     = SerializerFactory::createGatewaySerializer(EstPos::class);

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $serializer,
                $this->createMock(EventDispatcher::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->expects($this->never())->method('send');

        $posMock->make3DPayment($request, $order, $txType, $this->card);

        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertFalse($posMock->isSuccess());
    }

    /**
     * @return void
     */
    public function testMake3DHostPaymentSuccess()
    {
        $testData = EstPosResponseDataMapperTest::threeDHostPaymentDataProvider()['success1'];
        $request  = Request::create('', 'POST', $testData['paymentData']);
        $order    = $testData['order'];
        $txType   = $testData['txType'];

        $pos = $this->pos;

        $pos->make3DHostPayment($request, $order, $txType);

        $result = $pos->getResponse();
        $this->assertIsArray($result);
        $this->assertTrue($pos->isSuccess());
    }

    /**
     * @return void
     */
    public function testMake3DPayPaymentSuccess()
    {
        $testData = EstPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1'];
        $request  = Request::create('', 'POST', $testData['paymentData']);
        $order    = $testData['order'];
        $txType   = $testData['txType'];

        $pos = $this->pos;

        $pos->make3DPayPayment($request, $order, $txType);

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

        $crypt          = CryptFactory::createGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper  = RequestDataMapperFactory::createGatewayRequestMapper(EstPos::class, $this->createMock(EventDispatcherInterface::class), $crypt, []);
        $responseMapper = ResponseDataMapperFactory::createGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());
        $serializer     = SerializerFactory::createGatewaySerializer(EstPos::class);

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $serializer,
                $this->createMock(EventDispatcher::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->expects($this->never())->method('send');

        $posMock->make3DPayment($request, $this->order, PosInterface::TX_TYPE_PAY, $this->card);

        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertFalse($posMock->isSuccess());
    }

    /**
     * @dataProvider statusDataProvider
     */
    public function testStatus(array $testData, bool $isSuccess): void
    {
        $crypt          = CryptFactory::createGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper  = RequestDataMapperFactory::createGatewayRequestMapper(EstPos::class, $this->createMock(EventDispatcherInterface::class), $crypt, []);
        $responseMapper = ResponseDataMapperFactory::createGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());
        $serializer     = SerializerFactory::createGatewaySerializer(EstPos::class);

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $serializer,
                $this->createMock(EventDispatcher::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send', 'getQueryAPIUrl'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn($testData);
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
        $requestMapper = $this->createMock(RequestDataMapperInterface::class);
        $requestMapper->expects($this->once())->method('createHistoryRequestData')->willReturn([]);

        $responseMapper = $this->createMock(EstPosResponseDataMapper::class);
        $responseMapper->expects($this->once())->method('mapHistoryResponse')
            ->willReturn(EstPosResponseDataMapperTest::historyTestDataProvider()['success1']['expectedData']);

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $this->createMock(SerializerInterface::class),
                $this->createMock(EventDispatcher::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn(
            EstPosResponseDataMapperTest::historyTestDataProvider()['success1']['responseData']
        );

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
        $requestMapper = $this->createMock(RequestDataMapperInterface::class);
        $requestMapper->expects($this->once())->method('createHistoryRequestData')->willReturn([]);

        $responseMapper = $this->createMock(EstPosResponseDataMapper::class);
        $responseMapper->expects($this->once())->method('mapHistoryResponse')
            ->willReturn(EstPosResponseDataMapperTest::historyTestDataProvider()['fail1']['expectedData']);

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $this->createMock(SerializerInterface::class),
                $this->createMock(EventDispatcher::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn(
            EstPosResponseDataMapperTest::historyTestDataProvider()['fail1']['responseData']
        );

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
        $crypt          = CryptFactory::createGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper  = RequestDataMapperFactory::createGatewayRequestMapper(EstPos::class, $this->createMock(EventDispatcherInterface::class), $crypt, []);
        $responseMapper = ResponseDataMapperFactory::createGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());
        $serializer     = SerializerFactory::createGatewaySerializer(EstPos::class);

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $serializer,
                $this->createMock(EventDispatcher::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn(
            EstPosResponseDataMapperTest::cancelTestDataProvider()['success1']['responseData']
        );

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
        $crypt          = CryptFactory::createGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper  = RequestDataMapperFactory::createGatewayRequestMapper(EstPos::class, $this->createMock(EventDispatcherInterface::class), $crypt, []);
        $responseMapper = ResponseDataMapperFactory::createGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());
        $serializer     = SerializerFactory::createGatewaySerializer(EstPos::class);

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $serializer,
                $this->createMock(EventDispatcher::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn(
            EstPosResponseDataMapperTest::cancelTestDataProvider()['fail1']['responseData']
        );

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
        $crypt          = CryptFactory::createGatewayCrypt(EstPos::class, new NullLogger());
        $requestMapper  = RequestDataMapperFactory::createGatewayRequestMapper(EstPos::class, $this->createMock(EventDispatcherInterface::class), $crypt, []);
        $responseMapper = ResponseDataMapperFactory::createGatewayResponseMapper(EstPos::class, $requestMapper, new NullLogger());
        $serializer     = SerializerFactory::createGatewaySerializer(EstPos::class);

        $posMock = $this->getMockBuilder(EstPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $serializer,
                $this->createMock(EventDispatcher::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $posMock->expects($this->once())->method('send')->willReturn(
            EstPosResponseDataMapperTest::refundTestDataProvider()['fail1']['responseData']
        );

        $posMock->refund($this->order);

        $result = $posMock->getResponse();
        $this->assertIsArray($result);
        $this->assertFalse($posMock->isSuccess());
    }

    public static function statusDataProvider(): iterable
    {
        yield [
            'responseData' => EstPosResponseDataMapperTest::statusTestDataProvider()['fail1']['responseData'],
            'isSuccess'    => false,
        ];
        yield [
            'responseData' => EstPosResponseDataMapperTest::statusTestDataProvider()['success1']['responseData'],
            'isSuccess'    => true,
        ];
    }
}

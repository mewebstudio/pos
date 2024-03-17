<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Exception;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Factory\RequestDataMapperFactory;
use Mews\Pos\Factory\ResponseDataMapperFactory;
use Mews\Pos\Factory\SerializerFactory;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\PosNetV1Pos
 */
class PosNetV1PosTest extends TestCase
{
    private PosNetAccount $account;

    private array $config;

    private CreditCardInterface $card;

    /** @var PosNetV1Pos */
    private PosInterface $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../../config/pos_test.php';

        $this->account = AccountFactory::createPosNetAccount(
            'albaraka',
            '6700950031',
            '67540050',
            '1010028724242434',
            PosInterface::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $this->pos = PosFactory::createPosGateway($this->account, $this->config, $this->createMock(EventDispatcherInterface::class));

        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '21', '12', '122', 'ahmet');
    }

    /**
     * @return void
     */
    public function testInit(): void
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
    }

    /**
     * @dataProvider getApiURLDataProvider
     */
    public function testGetApiURL(string $txType, string $expected): void
    {
        $this->assertSame($expected, $this->pos->getApiURL($txType));
    }

    /**
     * @dataProvider make3dPaymentTestProvider
     *
     * @throws Exception
     */
    public function testMake3DPayment(array $order, array $threeDResponseData, array $paymentResponseData, array $expectedData): void
    {
        $request        = Request::create('', 'POST', $threeDResponseData);
        $crypt          = CryptFactory::createGatewayCrypt(PosNetV1Pos::class, new NullLogger());
        $requestMapper  = RequestDataMapperFactory::createGatewayRequestMapper(PosNetV1Pos::class, $this->createMock(EventDispatcherInterface::class), $crypt, []);
        $responseMapper = ResponseDataMapperFactory::createGatewayResponseMapper(PosNetV1Pos::class, $requestMapper, new NullLogger());
        $serializer     = SerializerFactory::createGatewaySerializer(PosNetV1Pos::class);

        $posMock = $this->getMockBuilder(PosNetV1Pos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $serializer,
                $this->createMock(EventDispatcherInterface::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['send'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->expects($this->exactly(1))->method('send')->willReturn($paymentResponseData);

        $posMock->make3DPayment($request, $order, PosInterface::TX_TYPE_PAY_AUTH, $this->card);
        $resp = $posMock->getResponse();
        unset($resp['transaction_time'], $expectedData['transaction_time']);
        unset($resp['all'], $resp['3d_all']);

        \ksort($expectedData);
        \ksort($resp);
        $this->assertSame($expectedData, $resp);
    }

    public static function make3dPaymentTestProvider(): iterable
    {
        $dataSamples  = iterator_to_array(PosNetV1PosResponseDataMapperTest::threeDPaymentDataProvider());
        $success1Data = $dataSamples['success1'];
        yield 'success1' => [
            'order'               => [
                'id'          => $success1Data['expectedData']['order_id'],
                'amount'      => 1.75,
                'installment' => 0,
                'currency'    => PosInterface::CURRENCY_TRY,
                'success_url' => 'https://domain.com/success',
            ],
            'threeDResponseData'  => $success1Data['threeDResponseData'],
            'paymentResponseData' => $success1Data['paymentData'],
            'expectedData'        => $success1Data['expectedData'],
        ];
    }

    public static function getApiURLDataProvider(): iterable
    {
        yield [
            'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected' => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/Sale',
        ];

        yield [
            'txType'   => PosInterface::TX_TYPE_CANCEL,
            'expected' => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/Reverse',
        ];
    }
}

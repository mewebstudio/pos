<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Exception;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

/**
 * PosNetV1Pos tests
 */
class PosNetV1PosTest extends TestCase
{
    /** @var PosNetAccount */
    private $account;

    private $config;

    /** @var AbstractCreditCard */
    private $card;

    /** @var PosNetV1Pos */
    private $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $this->account = AccountFactory::createPosNetAccount(
            'albaraka',
            '6700950031',
            '67540050',
            '1010028724242434',
            PosInterface::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $this->pos = PosFactory::createPosGateway($this->account, $this->config);

        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '21', '12', '122', 'ahmet');
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
     * @dataProvider getApiURLDataProvider
     */
    public function testGetApiURL(string $txType, string $expected)
    {
        $this->assertSame($expected, $this->pos->getApiURL($txType));
    }

    /**
     * @dataProvider make3dPaymentTestProvider
     *
     * @throws Exception
     */
    public function testMake3DPayment(array $order, array $threeDResponseData, array $paymentResponseData, array $expectedData)
    {
        $request = Request::create('', 'POST', $threeDResponseData);
        $crypt = PosFactory::getGatewayCrypt(PosNetV1Pos::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(PosNetV1Pos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(PosNetV1Pos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(PosNetV1Pos::class)
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
        $posMock->setTestMode(true);
        $posMock->expects($this->exactly(1))->method('send')->willReturn($paymentResponseData);

        $posMock->make3DPayment($request, $order, PosInterface::TX_PAY, $this->card);
        $resp = $posMock->getResponse();
        unset($resp['all'], $resp['3d_all']);

        $this->assertSame($expectedData, $resp);
    }

    public static function make3dPaymentTestProvider(): iterable
    {
        $dataSamples = iterator_to_array(PosNetV1PosResponseDataMapperTest::threeDPaymentDataProvider());

        yield 'success1' => [
            'order' => [
                'id'          => $dataSamples['success1']['threeDResponseData']['OrderId'],
                'amount'      => 1.75,
                'installment' => 0,
                'currency'    => PosInterface::CURRENCY_TRY,
                'success_url' => 'https://domain.com/success',
            ],
            'threeDResponseData' => $dataSamples['success1']['threeDResponseData'],
            'paymentResponseData' => $dataSamples['success1']['paymentData'],
            'expectedData' => $dataSamples['success1']['expectedData'],
        ];
    }

    public static function getApiURLDataProvider(): iterable
    {
        yield [
            'txType' => PosInterface::TX_PAY,
            'expected' => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/Sale',
        ];

        yield [
            'txType' => PosInterface::TX_CANCEL,
            'expected' => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/Reverse',
        ];
    }
}

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
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\PosNetV1Pos;
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

    private $order;

    /** @var PosNetV1Pos */
    private $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createPosNetAccount(
            'albaraka',
            '6700950031',
            'XXXXXX',
            'XXXXXX',
            '67540050',
            '1010028724242434',
            AbstractGateway::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );


        $this->order = [
            'id'          => '190620093100_024',
            'amount'      => 1.75,
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => AbstractGateway::LANG_TR,
            'rand'        => microtime(),
        ];

        $this->pos = PosFactory::createPosGateway($this->account);

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
        $this->pos->prepare($this->order, $txType);
        $this->assertSame($expected, $this->pos->getApiURL());
    }

    /**
     * @return void
     */
    public function testPrepare()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());

        $this->order['ref_ret_num'] = 'zz';
        $this->pos->prepare($this->order, AbstractGateway::TX_POST_PAY);

        $this->pos->prepare($this->order, AbstractGateway::TX_REFUND);
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
        $posMock->prepare($order, AbstractGateway::TX_PAY, $this->card);
        $posMock->expects($this->exactly(1))->method('send')->willReturn($paymentResponseData);

        $posMock->make3DPayment($request);
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
                'currency'    => 'TRY',
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
            'txType' => AbstractGateway::TX_PAY,
            'expected' => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/Sale',
        ];

        yield [
            'txType' => AbstractGateway::TX_CANCEL,
            'expected' => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/Reverse',
        ];
    }
}

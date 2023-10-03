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
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\DataMapper\ResponseDataMapper\PosNetResponseDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * PosNetTest
 */
class PosNetTest extends TestCase
{
    /** @var PosNetAccount */
    private $account;

    private $config;

    /** @var AbstractCreditCard */
    private $card;

    private $order;

    /** @var PosNet */
    private $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $this->account = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706598320',
            '67005551',
            '27426',
            PosInterface::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );


        $this->order = [
            'id'          => 'YKB_TST_190620093100_024',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => '1.75',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
            'rand'        => microtime(),
        ];

        $this->pos = PosFactory::createPosGateway($this->account, $this->config, new EventDispatcher());

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
     * @return void
     *
     * @throws Exception
     */
    public function testGet3DFormDataOosTransactionFail()
    {
        $this->expectException(Exception::class);
        $requestMapper  = PosFactory::getGatewayRequestMapper(PosNet::class, $this->createMock(EventDispatcherInterface::class));
        $responseMapper = PosFactory::getGatewayResponseMapper(PosNet::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(PosNet::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $this->createMock(SerializerInterface::class),
                $this->createMock(EventDispatcherInterface::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger(),
            ])
            ->onlyMethods(['getOosTransactionData'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->expects($this->once())->method('getOosTransactionData')->willReturn($this->getSampleOoTransactionFailResponseData());

        $posMock->get3DFormData($this->order, PosInterface::MODEL_3D_SECURE, PosInterface::TX_PAY, $this->card);
    }


    /**`
     * @return void
     *
     * @throws Exception
     */
    public function testMake3DPaymentSuccess()
    {
        $responseMapperTest = new PosNetResponseDataMapperTest();
        $request            = Request::create('', 'POST', [
            'MerchantPacket' => '',
            'BankPacket'     => '',
            'Sign'           => '',
        ]);
        $crypt              = PosFactory::getGatewayCrypt(PosNet::class, new NullLogger());
        $requestMapper      = PosFactory::getGatewayRequestMapper(PosNet::class, $this->createMock(EventDispatcherInterface::class), [], $crypt);
        $responseMapper     = PosFactory::getGatewayResponseMapper(PosNet::class, $requestMapper, new NullLogger());
        $serializer     = PosFactory::getGatewaySerializer(PosNet::class);

        $this->order['id'] = 'YKB_0000080603153823';
        $posMock           = $this->getMockBuilder(PosNet::class)
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

        $bankResponses = $responseMapperTest->threeDPaymentDataProvider()['success1'];
        $posMock->expects($this->exactly(2))->method('send')->will(
            $this->onConsecutiveCalls(
                $bankResponses['threeDResponseData'],
                $bankResponses['paymentData']
            )
        );

        $posMock->make3DPayment($request, $this->order, PosInterface::TX_PAY, $this->card);
        $resp = $posMock->getResponse();
        unset($resp['all'], $resp['3d_all']);

        $this->assertSame($bankResponses['expectedData'], $resp);
    }

    /**
     * @return string[]
     */
    private function getSampleOoTransactionFailResponseData(): array
    {
        return [
            'approved' => '0',
            'respCode' => '0003',
            'respText' => '148 MID,TID,IP HATALI:89.244.149.137',
        ];
    }
}

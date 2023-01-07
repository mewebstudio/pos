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
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Tests\DataMapper\ResponseDataMapper\PosNetResponseDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706598320',
            'XXXXXX',
            'XXXXXX',
            '67005551',
            '27426',
            AbstractGateway::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );


        $this->order = [
            'id'          => 'YKB_TST_190620093100_024',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => '1.75',
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
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
     * @return void
     *
     * @throws Exception
     */
    public function testGet3DFormDataOosTransactionFail()
    {
        $this->expectException(Exception::class);
        $requestMapper = PosFactory::getGatewayRequestMapper(PosNet::class);
        $responseMapper = PosFactory::getGatewayResponseMapper(PosNet::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(PosNet::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['getOosTransactionData'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $posMock->expects($this->once())->method('getOosTransactionData')->willReturn($this->getSampleOoTransactionFailResponseData());

        $posMock->get3DFormData();
    }


    /**
     * @return void
     *
     * @throws Exception
     */
    public function testMake3DPaymentSuccess()
    {
        $responseMapperTest = new PosNetResponseDataMapperTest();
        $request = Request::create('', 'POST', [
            'MerchantPacket' => '',
            'BankPacket' => '',
            'Sign' => '',
        ]);
        $crypt = PosFactory::getGatewayCrypt(PosNet::class, new NullLogger());
        $requestMapper = PosFactory::getGatewayRequestMapper(PosNet::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(PosNet::class, $requestMapper, new NullLogger());

        $this->order['id'] = 'YKB_0000080603153823';
        $posMock = $this->getMockBuilder(PosNet::class)
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
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $bankResponses = $responseMapperTest->threeDPaymentDataProvider()['success1'];
        $posMock->expects($this->exactly(2))->method('send')->will(
            $this->onConsecutiveCalls(
                    $bankResponses['threeDResponseData'],
                    $bankResponses['paymentData']
            )
        );

        $posMock->make3DPayment($request);
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

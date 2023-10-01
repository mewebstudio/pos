<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Gateways;

use Exception;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\DataMapper\PayFlexV4PosRequestDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * PayFlexV4PosTest
 */
class PayFlexV4PosTest extends TestCase
{
    /** @var PayFlexAccount */
    private $account;

    /** @var PayFlexV4Pos */
    private $pos;

    private $config;

    /** @var AbstractCreditCard */
    private $card;

    /** @var array */
    private $order = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $this->account = AccountFactory::createPayFlexAccount(
            'vakifbank',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            PosInterface::MODEL_3D_SECURE
        );


        $this->order = [
            'id'          => 'order222',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'rand'        => microtime(true),
            'extraData'   => microtime(true),
            'ip'          => '127.0.0.1',
        ];

        $this->pos = PosFactory::createPosGateway($this->account, $this->config, new EventDispatcher());

        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '2021', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
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
    public function testGet3DFormDataEnrollmentFail(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(2005);

        $requestMapper  = PosFactory::getGatewayRequestMapper(PayFlexV4Pos::class);
        $responseMapper = PosFactory::getGatewayResponseMapper(PayFlexV4Pos::class, $requestMapper, new NullLogger());
        $serializer     = PosFactory::getGatewaySerializer(PayFlexV4Pos::class);

        $posMock = $this->getMockBuilder(PayFlexV4Pos::class)
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
            ->onlyMethods(['sendEnrollmentRequest'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->expects($this->once())->method('sendEnrollmentRequest')
            ->willReturn(PayFlexV4PosRequestDataMapperTest::getSampleEnrollmentFailResponseDataProvider());

        $posMock->get3DFormData($this->order, PosInterface::MODEL_3D_SECURE, PosInterface::TX_PAY, $this->card);
    }

    public function testGet3DFormDataSuccess(): void
    {
        $enrollmentResponse = PayFlexV4PosRequestDataMapperTest::getSampleEnrollmentSuccessResponseDataProvider();

        $requestMapper  = PosFactory::getGatewayRequestMapper(PayFlexV4Pos::class);
        $responseMapper = PosFactory::getGatewayResponseMapper(PayFlexV4Pos::class, $requestMapper, new NullLogger());
        $serializer     = PosFactory::getGatewaySerializer(PayFlexV4Pos::class);

        $posMock = $this->getMockBuilder(PayFlexV4Pos::class)
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
            ->onlyMethods(['sendEnrollmentRequest'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->expects($this->once())->method('sendEnrollmentRequest')
            ->willReturn($enrollmentResponse);

        $result   = $posMock->get3DFormData($this->order, PosInterface::MODEL_3D_SECURE, PosInterface::TX_PAY, $this->card);
        $expected = [
            'gateway' => $enrollmentResponse['Message']['VERes']['ACSUrl'],
            'method'  => 'POST',
            'inputs'  => [
                'PaReq'   => $enrollmentResponse['Message']['VERes']['PaReq'],
                'TermUrl' => $enrollmentResponse['Message']['VERes']['TermUrl'],
                'MD'      => $enrollmentResponse['Message']['VERes']['MD'],
            ],
        ];

        $this->assertSame($expected, $result);
    }
}

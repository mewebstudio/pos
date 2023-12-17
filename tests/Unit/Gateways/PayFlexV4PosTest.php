<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Exception;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Factory\RequestDataMapperFactory;
use Mews\Pos\Factory\ResponseDataMapperFactory;
use Mews\Pos\Factory\SerializerFactory;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\PayFlexV4PosRequestDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers \Mews\Pos\Gateways\PayFlexV4Pos
 */
class PayFlexV4PosTest extends TestCase
{
    private PayFlexAccount $account;

    /** @var PayFlexV4Pos */
    private PosInterface $pos;

    private array $config;

    private CreditCardInterface $card;

    private array $order = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../../config/pos_test.php';

        $this->account = AccountFactory::createPayFlexAccount(
            'vakifbank',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            PosInterface::MODEL_3D_SECURE
        );


        $this->order = [
            'id'          => 'order222',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'ip'          => '127.0.0.1',
        ];

        $this->pos = PosFactory::createPosGateway($this->account, $this->config, new EventDispatcher());

        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '2021', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
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

        $requestMapper  = RequestDataMapperFactory::createGatewayRequestMapper(
            PayFlexV4Pos::class,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class)
        );
        $responseMapper = ResponseDataMapperFactory::createGatewayResponseMapper(PayFlexV4Pos::class, $requestMapper, new NullLogger());
        $serializer     = SerializerFactory::createGatewaySerializer(PayFlexV4Pos::class);

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

        $posMock->get3DFormData($this->order, PosInterface::MODEL_3D_SECURE, PosInterface::TX_TYPE_PAY, $this->card);
    }

    public function testGet3DFormDataSuccess(): void
    {
        $enrollmentResponse = PayFlexV4PosRequestDataMapperTest::getSampleEnrollmentSuccessResponseDataProvider();

        $requestMapper  = RequestDataMapperFactory::createGatewayRequestMapper(
            PayFlexV4Pos::class,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class)
        );
        $responseMapper = ResponseDataMapperFactory::createGatewayResponseMapper(PayFlexV4Pos::class, $requestMapper, new NullLogger());
        $serializer     = SerializerFactory::createGatewaySerializer(PayFlexV4Pos::class);

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

        $result   = $posMock->get3DFormData($this->order, PosInterface::MODEL_3D_SECURE, PosInterface::TX_TYPE_PAY, $this->card);
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

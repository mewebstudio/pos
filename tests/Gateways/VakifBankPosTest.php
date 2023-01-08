<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Exception;
use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\VakifBankPos;
use Mews\Pos\Tests\DataMapper\VakifBankPosRequestDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * VakifBankPosTest
 */
class VakifBankPosTest extends TestCase
{
    /**
     * @var VakifBankAccount
     */
    private $account;
    /**
     * @var VakifBankPos
     */
    private $pos;
    private $config;

    /**
     * @var AbstractCreditCard
     */
    private $card;

    /** @var array */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createVakifBankAccount(
            'vakifbank',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            AbstractGateway::MODEL_3D_SECURE
        );


        $this->order = [
            'id'          => 'order222',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'rand'        => microtime(true),
            'extraData'   => microtime(true),
            'ip'          => '127.0.0.1',
        ];

        $this->pos = PosFactory::createPosGateway($this->account);

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
     */
    public function testPrepare()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());

        $this->pos->prepare($this->order, AbstractGateway::TX_POST_PAY);
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testGet3DFormDataEnrollmentFail()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(2005);
        $vakifBankPosRequestDataMapperTest = new VakifBankPosRequestDataMapperTest();

        $requestMapper = PosFactory::getGatewayRequestMapper(VakifBankPos::class);
        $responseMapper = PosFactory::getGatewayResponseMapper(VakifBankPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(VakifBankPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['sendEnrollmentRequest'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $posMock->expects($this->once())->method('sendEnrollmentRequest')
            ->willReturn($vakifBankPosRequestDataMapperTest->getSampleEnrollmentFailResponseData());

        $posMock->get3DFormData();
    }

    public function testGet3DFormDataSuccess()
    {
        $vakifBankPosRequestDataMapperTest = new VakifBankPosRequestDataMapperTest();
        $enrollmentResponse = $vakifBankPosRequestDataMapperTest->getSampleEnrollmentSuccessResponseData();

        $requestMapper = PosFactory::getGatewayRequestMapper(VakifBankPos::class);
        $responseMapper = PosFactory::getGatewayResponseMapper(VakifBankPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(VakifBankPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['sendEnrollmentRequest'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $posMock->expects($this->once())->method('sendEnrollmentRequest')
            ->willReturn($enrollmentResponse);

        $result = $posMock->get3DFormData();
        $expected = [
            'gateway' => $enrollmentResponse['Message']['VERes']['ACSUrl'],
            'inputs' => [
                'PaReq'   => $enrollmentResponse['Message']['VERes']['PaReq'],
                'TermUrl' => $enrollmentResponse['Message']['VERes']['TermUrl'],
                'MD'      => $enrollmentResponse['Message']['VERes']['MD'],
            ],
        ];

        $this->assertSame($expected, $result);
    }
}

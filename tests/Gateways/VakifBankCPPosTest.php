<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Exception;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifBankCPPosResponseDataMapper;
use Mews\Pos\DataMapper\VakifBankCPPosRequestDataMapper;
use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\VakifBankCPPos;
use Mews\Pos\Tests\DataMapper\VakifBankCPPosRequestDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class VakifBankCPPosTest extends TestCase
{
    /** @var VakifBankAccount */
    private $account;

    /** @var VakifBankCPPos */
    private $pos;

    private $config;

    /** @var AbstractCreditCard */
    private $card;

    /** @var array */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createVakifBankAccount(
            'vakifbank-cp',
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
    public function testInit(): void
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
    }

    /**
     * @return void
     */
    public function testPrepare(): void
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());

        $this->pos->prepare($this->order, AbstractGateway::TX_POST_PAY);
    }

    public function testGet3DFormDataSuccess(): void
    {
        $crypt          = PosFactory::getGatewayCrypt(VakifBankCPPos::class, new NullLogger());
        $requestMapper  = PosFactory::getGatewayRequestMapper(VakifBankCPPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(VakifBankCPPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(VakifBankCPPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['registerPayment'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $posMock->expects($this->once())->method('registerPayment')
            ->willReturn(VakifBankCPPosRequestDataMapperTest::threeDFormDataProvider()->current()['queryParams']);

        $result = $posMock->get3DFormData();

        $this->assertSame(VakifBankCPPosRequestDataMapperTest::threeDFormDataProvider()->current()['expected'], $result);
    }

    public function testGet3DFormDataFail(): void
    {
        $this->expectException(Exception::class);
        $posMock = $this->getMockBuilder(VakifBankCPPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $this->createMock(VakifBankCPPosRequestDataMapper::class),
                $this->createMock(VakifBankCPPosResponseDataMapper::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['registerPayment'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $posMock->expects($this->once())->method('registerPayment')
            ->willReturn([
                'CommonPaymentUrl' => null,
                'PaymentToken'     => null,
                'ErrorCode'        => '5007',
                'ResponseMessage'  => 'Güvenlik Numarası Hatalı',
            ]);

        $posMock->get3DFormData();
    }
}

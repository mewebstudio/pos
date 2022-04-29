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
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\PosNet;
use PHPUnit\Framework\TestCase;

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
            'currency'    => 'TL',
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
        $this->assertSame('TL', $this->pos->getOrder()->currency);
        $this->assertEquals($this->card, $this->pos->getCard());

        $this->order['host_ref_num'] = 'zz';
        $this->pos->prepare($this->order, AbstractGateway::TX_POST_PAY);
        $this->assertSame('TL', $this->pos->getOrder()->currency);

        $this->pos->prepare($this->order, AbstractGateway::TX_REFUND);
        $this->assertSame('TL', $this->pos->getOrder()->currency);
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testGet3DFormDataOosTransactionFail()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(3);

        $posMock = $this->getMockBuilder(PosNet::class)
            ->setConstructorArgs([[], $this->account, PosFactory::getGatewayMapper(PosNet::class)])
            ->onlyMethods(['getOosTransactionData'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $posMock->expects($this->once())->method('getOosTransactionData')->willReturn($this->getSampleOoTransactionFailResponseData());

        $posMock->get3DFormData();
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

    public function testVerifyResponseMAC()
    {
        $newOrder             = $this->order;
        $newOrder['id']       = '895';
        $newOrder['amount']   = 1;
        $newOrder['currency'] = 'TL';

        $account = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706598320',
            'XXXXXX',
            'XXXXXX',
            '67825768',
            '27426',
            AbstractGateway::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );
        /** @var PosNet $pos */
        $pos = PosFactory::createPosGateway($account);
        $pos->setTestMode(true);

        $pos->prepare($newOrder, AbstractGateway::TX_PAY);
        $data = (object) [
            'mdStatus' => '9',
            'mac'      => 'U2kU/JWjclCvKZjILq8xBJUXhyB4DswKvN+pKfxl0u0=',
        ];
        $this->assertTrue($pos->verifyResponseMAC($pos->getAccount(), $pos->getOrder(), $data));

        $newOrder['id'] = '800';
        $pos->prepare($newOrder, AbstractGateway::TX_PAY);
        $data = (object) [
            'mdStatus' => '9',
            'mac'      => 'U2kU/JWjclCvKZjILq8xBJUXhyB4DswKvN+pKfxl0u0=',
        ];
        $this->assertFalse($pos->verifyResponseMAC($pos->getAccount(), $pos->getOrder(), $data));
    }
}

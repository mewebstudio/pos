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

        $this->order['host_ref_num'] = 'zz';
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

    public function testVerifyResponseMAC()
    {
        $newOrder             = $this->order;
        $newOrder['id']       = '895';
        $newOrder['amount']   = 1;
        $newOrder['currency'] = 'TRY';

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

    public function testCheck3DHash()
    {
        $account = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706022701',
            'XXXXXX',
            'XXXXXX',
            '67002706',
            '27426',
            AbstractGateway::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $pos = PosFactory::createPosGateway($account);

        $data = [
            'MerchantPacket' => 'F57E38055C280283044612E7338A314758CE0BB13FE9CFF2D1ACD415A979C1C65AD1FA664E561809F63262552496B491378DE688980EDFEF32785CB8090E0F3F618D560B4C2C089C7B9FBA8F91F1F4231D6725ECF8D94B18B0AA9EA206083D94BA1315DCC950E7E5BED2B3B5A1571C3E761E2364E590CC6BB95BF4F1165208FA55CE99BDE6C7ACDEFB5A2A6F16B6C3838B9876F00EDF1E7261B626532EE81C40C9DE94588ED36FC4D2E639FA89152D1590A0031416BA8A31A1300EE37E31BD54B6ADA2FF7D4D58EA0A4A1CC7',
        ];
        $order = [
            'id' => 'YKB_0000080603153823',
            'amount' => 56.96,
            'currency' => 'TRY',
            'installment' => 0,
        ];
        $pos->prepare($order, AbstractGateway::TX_PAY);
        $result = $pos->check3DHash($data);
        $this->assertTrue($result);

        $order['amount'] = 56.97;
        $pos->prepare($order, AbstractGateway::TX_PAY);
        $result = $pos->check3DHash($data);
        $this->assertFalse($result);

        $order['amount'] = 56.96;
        $pos->prepare($order, AbstractGateway::TX_PAY);
        $data['MerchantPacket'] = $data['MerchantPacket'].'2';
        $result = $pos->check3DHash($data);
        $this->assertFalse($result);
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

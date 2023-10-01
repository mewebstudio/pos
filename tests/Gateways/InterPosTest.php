<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\DataMapper\ResponseDataMapper\InterPosResponseDataMapperTest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\InterPos
 */
class InterPosTest extends TestCase
{
    /** @var InterPosAccount */
    private $account;

    /** @var InterPos */
    private $pos;

    /** @var AbstractCreditCard */
    private $card;

    private $config;

    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $userCode     = 'InterTestApi';
        $userPass     = '3';
        $shopCode     = '3123';
        $merchantPass = 'gDg1N';

        $this->account = AccountFactory::createInterPosAccount(
            'denizbank',
            $shopCode,
            $userCode,
            $userPass,
            PosInterface::MODEL_3D_SECURE,
            $merchantPass
        );

        $this->order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
            'rand'        => microtime(true),
        ];

        $this->pos = PosFactory::createPosGateway($this->account, $this->config, new EventDispatcher());

        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '21', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
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
    public function testMake3DPaymentAuthFail()
    {
        $interPosResponseDataMapperTest = new InterPosResponseDataMapperTest();
        $gatewayResponse = $interPosResponseDataMapperTest->threeDPaymentDataProvider()['authFail1']['threeDResponseData'];
        $request = Request::create('', 'POST', $gatewayResponse);

        $this->pos->make3DPayment($request, $this->order, PosInterface::TX_PAY, $this->card);
        $result = $this->pos->getResponse();
        $this->assertIsArray($result);

        $this->assertSame('declined', $result['status']);
        $this->assertSame('81', $result['proc_return_code']);
        $this->assertNotEmpty($result['3d_all']);
        $this->assertEmpty($result['all']);
    }

    /**
     * @return void
     */
    public function testMake3DPayPaymentFail()
    {
        $interPosResponseDataMapperTest = new InterPosResponseDataMapperTest();
        $gatewayResponse = $interPosResponseDataMapperTest->threeDPayPaymentDataProvider()['authFail1']['paymentData'];
        $request = Request::create('', 'POST', $gatewayResponse);

        $this->pos->make3DPayment($request, $this->order, PosInterface::TX_PAY, $this->card);
        $result = $this->pos->getResponse();
        $this->assertIsArray($result);

        $this->assertSame('declined', $result['status']);
        $this->assertSame('81', $result['proc_return_code']);
        $this->assertNotEmpty($result['3d_all']);
        $this->assertEmpty($result['all']);
    }
}

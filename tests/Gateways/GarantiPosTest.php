<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\GarantiPos;
use PHPUnit\Framework\TestCase;

/**
 * GarantiPosTest
 */
class GarantiPosTest extends TestCase
{
    /** @var GarantiPosAccount */
    private $account;
    private $config;

    /** @var AbstractCreditCard */
    private $card;
    private $order;

    /** @var GarantiPos */
    private $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            AbstractGateway::MODEL_3D_SECURE,
            '12345678',
            'PROVRFN',
            '123qweASD/'
        );

        $this->order = [
            'id'          => 'order222',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'rand'        => microtime(),
            'ip'          => '156.155.154.153',
        ];

        $this->pos = PosFactory::createPosGateway($this->account);
        $this->pos->setTestMode(true);
        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '21', '12', '122');
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
        $this->assertSame(0, $this->pos->getOrder()->installment);
    }
}

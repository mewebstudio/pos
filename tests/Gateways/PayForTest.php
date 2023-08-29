<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\PayForPos;
use PHPUnit\Framework\TestCase;

/**
 * PayForTest
 */
class PayForTest extends TestCase
{
    /** @var PayForAccount */
    private $threeDAccount;

    private $config;

    /** @var AbstractCreditCard */
    private $card;

    private $order;

    /** @var PayForPos */
    private $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $this->threeDAccount = AccountFactory::createPayForAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            AbstractGateway::MODEL_3D_SECURE,
            '12345678'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'email'       => 'mail@customer.com', // optional
            'name'        => 'John Doe', // optional
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'rand'        => '0.43625700 1604831630',
            'lang'        => AbstractGateway::LANG_TR,
        ];

        $this->pos = PosFactory::createPosGateway($this->threeDAccount, $this->config);

        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '22', '01', '123', 'ahmet');
    }

    /**
     * @return void
     */
    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->threeDAccount->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->threeDAccount, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
        $this->assertEquals($this->config['banks'][$this->threeDAccount->getBank()]['gateway_endpoints']['gateway_3d_host'], $this->pos->get3DHostGatewayURL());
        $this->assertEquals($this->config['banks'][$this->threeDAccount->getBank()]['gateway_endpoints']['gateway_3d'], $this->pos->get3DGatewayURL());
        $this->assertEquals($this->config['banks'][$this->threeDAccount->getBank()]['gateway_endpoints']['payment_api'], $this->pos->getApiURL());
    }

    /**
     * @return void
     */
    public function testSetTestMode()
    {
        $this->pos->setTestMode(false);
        $this->assertFalse($this->pos->isTestMode());
        $this->pos->setTestMode(true);
        $this->assertTrue($this->pos->isTestMode());
    }
}

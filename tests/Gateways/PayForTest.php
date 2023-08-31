<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * PayForTest
 */
class PayForTest extends TestCase
{
    /** @var PayForAccount */
    private $account;

    private $config;

    /** @var PayForPos */
    private $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $this->account = AccountFactory::createPayForAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            PosInterface::MODEL_3D_SECURE,
            '12345678'
        );

        $this->pos = PosFactory::createPosGateway($this->account, $this->config);
        $this->pos->setTestMode(true);
    }

    /**
     * @return void
     */
    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
        $this->assertEquals($this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d_host'], $this->pos->get3DHostGatewayURL());
        $this->assertEquals($this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d'], $this->pos->get3DGatewayURL());
        $this->assertEquals($this->config['banks'][$this->account->getBank()]['gateway_endpoints']['payment_api'], $this->pos->getApiURL());
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

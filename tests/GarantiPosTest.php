<?php

namespace Mews\Pos\Tests;

use Mews\Pos\GarantiPos;
use PHPUnit\Framework\TestCase;


class GarantiPosTest extends TestCase
{
    private $account;
    private $garantiPos;
    private $config;
    private $card;
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__ . '/../config/pos.php';

        $this->account = (object)[
            'bank' => 'akbank',
            'model' => '3d',
            'client_id' => 'XXXXXXX',
            'terminal_id' => '13456',
            'username' => 'XXXXXXX',
            'password' => 'XXXXXXX',
            'store_key' => 'XXXXXXX',
            'env' => 'test',
        ];

        $this->card = (object)[
            'number' => '5555444433332222',
            'year' => '21',
            'month' => '12',
            'cvv' => '122',
            'name' => 'ahmet',
            'type' => 'visa'
        ];

        $this->order = (object)[
            'id' => 'order222',
            'name' => 'siparis veren',
            'email' => 'test@test.com',
            'amount' => '100.25',
            'installment' => 0,
            'currency' => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url' => 'https://domain.com/fail_url',
            'lang' => 'tr',
            'rand' => microtime(),
            'ip' => '156.155.154.153'
        ];

        $this->garantiPos = new GarantiPos(
            $this->config['banks'][$this->account->bank],
            $this->account,
            $this->config['currencies']);
    }

    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->bank], $this->garantiPos->getConfig());
        $this->assertEquals($this->account, $this->garantiPos->getAccount());
        $this->assertEquals($this->config['currencies'], $this->garantiPos->getCurrencies());
    }

    public function testPrepare()
    {

        $this->garantiPos->prepare($this->order, $this->card);
        $this->assertEquals($this->card, $this->garantiPos->getCard());
        $this->assertEquals($this->order, $this->garantiPos->getOrder());
    }

    public function testGet3DFormData()
    {
        $this->garantiPos->prepare($this->order, $this->card);

        $form = [
            'gateway' => $this->config['banks'][$this->account->bank]['urls']['gateway'][$this->account->env],
            'success_url' => $this->order->success_url,
            'fail_url' => $this->order->fail_url,
            'rand' => $this->order->rand
        ];
        $actualForm = $this->garantiPos->get3DFormData();
        $this->assertNotEmpty($actualForm['inputs']);
        $this->assertNotEmpty($actualForm['hash']);

        unset($actualForm['inputs']);
        unset($actualForm['hash']);
        $this->assertEquals($form, $actualForm);
    }


}

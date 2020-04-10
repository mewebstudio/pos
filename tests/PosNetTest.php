<?php

namespace Mews\Pos\Tests;
use Mews\Pos\PosNet;
use PHPUnit\Framework\TestCase;



class PosNetTest extends TestCase
{
    private $account;
    private $posnet;
    private $config;

    private $card;
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = (object)[
            'bank' => 'yapikredi',
            'model' => 'regular',
            'client_id' => '6706598320',
            'terminal_id' => '67005551',
            'posnet_id' => '27426',
            'env' => 'test',
            'store_key' => '10,10,10,10,10,10,10,10',
            'model' => '3d'
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
            'id' => 'YKB_TST_190620093100_024',
            'name' => 'siparis veren',
            'email' => 'test@test.com',
            'amount' => '1.75',
            'installment' => 0,
            'currency' => 'TL',
            'success_url' => 'https://domain.com/success',
            'fail_url' => 'https://domain.com/fail_url',
            'lang' => 'tr',
            'rand' => microtime()
        ];


        $this->config = require __DIR__ . '/../config/pos.php';
        $this->posnet = new PosNet(
            $this->config['banks'][$this->account->bank],
            $this->account,
            $this->config['currencies']);
    }

    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->bank], $this->posnet->getConfig());
        $this->assertEquals($this->account, $this->posnet->getAccount());
        $this->assertEquals($this->config['currencies'], $this->posnet->getCurrencies());
    }

    public function testPrepare()
    {
        $this->posnet->prepare($this->order, $this->card);
        $this->assertEquals($this->card, $this->posnet->getCard());
        $this->assertEquals($this->order, $this->posnet->getOrder());
    }

    public function testCreate3DHash(){

        $this->posnet->prepare($this->order, $this->card);
        $this->assertEquals('J/7/Xprj7F/KDf98luVfIGyUPRQzUCqGwpmvz3KT7oQ=', $this->posnet->create3DHash());
    }

    public function testVerifyResponseMAC(){

        $order = $this->order;
        $order->id = '895';
        $order->amount = 1;
        $order->currency = 'TL';

        $account = $this->account;
        $account->client_id = '6706598320';
        $account->terminal_id = '67825768';
        $account->store_key = '10,10,10,10,10,10,10,10';

        $this->posnet->prepare($order, $account);
        $data = (object)[
          'mdStatus' => '9',
          'mac' => 'U2kU/JWjclCvKZjILq8xBJUXhyB4DswKvN+pKfxl0u0='
        ];
        $this->assertTrue($this->posnet->verifyResponseMAC($data));

        $order->id = '800';
        $this->posnet->prepare($order, $account);
        $data = (object)[
          'mdStatus' => '9',
          'mac' => 'U2kU/JWjclCvKZjILq8xBJUXhyB4DswKvN+pKfxl0u0='
        ];
        $this->assertFalse($this->posnet->verifyResponseMAC($data));
    }
}

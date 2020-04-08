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
            'terminal_id' => '67322946',
            'posnet_id' => '27426',
            'env' => 'test',
            'store_key' => '10,10,10,10,10,10,10,10'
        ];

        $this->account = (object)[
            'bank' => 'akbank',
            'model' => '3d',
            'client_id' => 'XXXXXXX',
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
}

<?php

namespace Mews\Pos\Tests;
use Mews\Pos\Pos;
use Mews\Pos\PosHelpersTrait;
use Mews\Pos\PosNet;
use PHPUnit\Framework\TestCase;



class PosTest extends TestCase
{
    use PosHelpersTrait;

    private $account;
    private $pos;
    private $config;

    private $card;
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__ . '/../config/pos.php';
        $this->account = [
            'bank' => 'yapikredi',
            'model' => 'regular',
            'client_id' => '6706598320',
            'terminal_id' => '67322946',
            'posnet_id' => '27426',
            'env' => 'test',
            'store_key' => '10,10,10,10,10,10,10,10'
        ];

        $this->card = [
            'number' => '5555444433332222',
            'year' => '21',
            'month' => '12',
            'cvv' => '122',
            'name' => 'ahmet',
            'type' => 'visa'
        ];

        $this->order = [
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

        $this->pos = new Pos($this->account);
    }

    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account['bank']], $this->pos->getConfig());
        $this->assertEquals((object)$this->account, $this->pos->getAccount());
        $this->assertEquals($this->config['currencies'], $this->pos->getCurrencies());
        $this->assertInstanceOf(PosNet::class, $this->pos->bank);
    }

    public function testCreateXML()
    {
        $xml_str = $this->createXML($this->order);
        $this->assertIsString($xml_str);
    }

    public function testXMLStringToObject()
    {
        $xml_str = $this->createXML(['order' => $this->order]);
        $this->assertEquals((object)$this->order, $this->XMLStringToObject($xml_str));
    }

    public function testPrepare()
    {

        $this->pos->prepare($this->order, $this->card);
        $this->assertEquals((object)$this->card, $this->pos->getCard());
        //$this->assertEquals((object)$order, $this->pos->getOrder());
    }

    public function testGetGatewayUrl()
    {
        $this->assertEquals($this->config['banks'][$this->account['bank']]['urls']['gateway'][$this->account['env']], $this->pos->getGatewayUrl());
    }
}

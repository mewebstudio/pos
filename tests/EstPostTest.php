<?php

namespace Mews\Pos\Tests;

use Mews\Pos\EstPos;
use PHPUnit\Framework\TestCase;


class EstPostTest extends TestCase
{
    private $account;
    private $estpos;
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
            'username' => 'XXXXXXX',
            'password' => 'XXXXXXX',
            'store_key' => 'VnM5WZ3sGrPusmWP',
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

        $this->estpos = new EstPos(
            $this->config['banks'][$this->account->bank],
            $this->account,
            $this->config['currencies']);
    }

    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->bank], $this->estpos->getConfig());
        $this->assertEquals($this->account, $this->estpos->getAccount());
        $this->assertEquals($this->config['currencies'], $this->estpos->getCurrencies());
    }

    public function testPrepare()
    {

        $this->estpos->prepare($this->order, $this->card);
        $this->assertEquals($this->card, $this->estpos->getCard());
        $this->assertEquals($this->order, $this->estpos->getOrder());
    }

    public function testGetCardCode()
    {
        $card = $this->card;

        $card->type = '1';
        $this->estpos->prepare($this->order, $card);
        $this->assertEquals($card->type, $this->estpos->getCardCode());

        $card->type = 'visa';
        $this->estpos->prepare($this->order, $card);
        $this->assertNotNull($this->estpos->getCardCode());

        $card->type = 'master';
        $this->estpos->prepare($this->order, $card);
        $this->assertNotNull($this->estpos->getCardCode());
    }

    public function testGet3DFormData()
    {
        $this->estpos->prepare($this->order, $this->card);

        $form = [
            'gateway' => $this->config['banks'][$this->account->bank]['urls']['gateway'][$this->account->env],
            'success_url' => $this->order->success_url,
            'fail_url' => $this->order->fail_url,
            'rand' => $this->order->rand,
            'hash' => $this->estpos->create3DHash(),
            'inputs' => [
                'clientid' => $this->account->client_id,
                'storetype' => $this->account->model,
                'hash' => $this->estpos->create3DHash(),
                'cardType' => $this->estpos->getCardCode(),
                'pan' => $this->card->number,
                'Ecom_Payment_Card_ExpDate_Month' => $this->card->month,
                'Ecom_Payment_Card_ExpDate_Year' => $this->card->year,
                'cv2' => $this->card->cvv,
                'firmaadi' => $this->order->name,
                'Email' => $this->order->email,
                'amount' => $this->order->amount,
                'oid' => $this->order->id,
                'okUrl' => $this->order->success_url,
                'failUrl' => $this->order->fail_url,
                'rnd' => $this->order->rand,
                'lang' => $this->order->lang,
                'currency' => $this->order->currency,
            ]
        ];
        $this->assertEquals($form, $this->estpos->get3DFormData());
    }

    public function testCheck3DHash()
    {
        $data = [
            "md" => "478719:0373D10CFD8BDED34FA0546D27D5BE76F8BA4A947D1EC499102AE97B880EB1B9:4242:##400902568",
            "cavv" => "BwAQAhIYRwEAABWGABhHEE6v5IU=",
            "AuthCode" => "",
            "oid" => "880",
            "mdStatus" => "4",
            "eci" => "06",
            "clientid" => "400902568",
            "rnd" => "hDx50d0cq7u1vbpWQMae",
            "ProcReturnCode" => "N7",
            "Response" => "Declined",
            "HASH" => "D+B5fFWXEWFqVSkwotyuTPUW800=",
            "HASHPARAMS" => "clientid:oid:AuthCode:ProcReturnCode:Response:mdStatus:cavv:eci:md:rnd:",
            "HASHPARAMSVAL" => "400902568880N7Declined4BwAQAhIYRwEAABWGABhHEE6v5IU=06478719:0373D10CFD8BDED34FA0546D27D5BE76F8BA4A947D1EC499102AE97B880EB1B9:4242:##400902568hDx50d0cq7u1vbpWQMae"
        ];

        $this->assertTrue($this->estpos->check3DHash($data));

        $data['mdStatus'] = '';
        $this->assertFalse($this->estpos->check3DHash($data));
    }
}

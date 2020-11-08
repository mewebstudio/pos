<?php

namespace Mews\Pos\Tests;

use Mews\Pos\Entity\Card\CreditCardEstPos;
use Mews\Pos\Entity\Card\CreditCardGarantiPos;
use Mews\Pos\Entity\Card\CreditCardPayFor;
use Mews\Pos\EstPos;
use Mews\Pos\PayForPos;
use PHPUnit\Framework\TestCase;

class PayForTest extends TestCase
{
    private $account;
    /**
     * @var PayForPos
     */
    private $payFor;
    private $config;

    /**
     * @var CreditCardPayFor
     */
    private $card;
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../config/pos.php';

        $this->account = (object) [
            'bank'          => 'qnbfinansbank-payfor',
            'model'         => '3d',
            'client_id'     => '085300000009704',
            'username'      => 'QNB_API_KULLANICI_3DPAY',
            'password'      => 'UcBN0',
            'store_key'     => '12345678', //MerchantPass only needed for 3D payment
            'env'           => 'test',
            'lang'          => PayForPos::LANG_EN,
            'customData'    => (object) [
                /**
                 * 0 : İşlemin E-commerce olduğunu ifade eder.
                 * 1 : İşlemin MO TO olduğunu ifade ede
                 */
                'moto' => '0',
                'mbrId' => 5, //Kurum Kodu
            ],
        ];

        $this->card = new CreditCardPayFor('5555444433332222', '22', '01', '123', 'ahmet');

        $this->order = (object) [
            'id'                => '2020110828BC',
            'email'             => 'mail@customer.com', // optional
            'name'              => 'John Doe', // optional
            'amount'            => 100.01,
            'installment'       => '0',
            'currency'          => 'TRY',
            'success_url'       => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'          => 'http://localhost/finansbank-payfor/3d/response.php',
            'transaction'       => 'pay', // pay => Auth, pre PreAuth,
            'rand'              => '0.43625700 1604831630',
            'hash'              => 'zmSUxYPhmCj7QOzqpk/28LuE1Oc=',
            'lang'              => PayForPos::LANG_TR,
        ];

        $this->payFor = new PayForPos(
            $this->config['banks'][$this->account->bank],
            $this->account,
            $this->config['currencies']
        );
    }

    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->bank], $this->payFor->getConfig());
        $this->assertEquals($this->account, $this->payFor->getAccount());
        $this->assertEquals($this->config['currencies'], $this->payFor->getCurrencies());
    }

    public function testPrepare()
    {

        $this->payFor->prepare($this->order, $this->card);
        $this->assertEquals($this->card, $this->payFor->getCard());
        $this->assertEquals($this->order, $this->payFor->getOrder());
    }

    public function testGet3DFormData()
    {
        $this->payFor->prepare($this->order, $this->card);

        $form = [
            'gateway' => $this->config['banks'][$this->account->bank]['urls']['gateway'][$this->account->env],
            'inputs' => [
                'MbrId' => $this->account->customData->mbrId,
                'MerchantID' => $this->account->client_id,
                'UserCode' => $this->account->username,
                'OrderId' => $this->order->id,
                'Lang' => $this->order->lang,
                'SecureType' => '3DModel',
                'TxnType' => 'Auth',
                'PurchAmount' => $this->order->amount,
                'InstallmentCount' => $this->order->installment,
                'Currency' => $this->order->currency,
                'OkUrl' => $this->order->success_url,
                'FailUrl' => $this->order->fail_url,
                'Rnd' => $this->order->rand,
                'Hash' => $this->payFor->create3DHash(),
            ]
        ];
        $this->assertEquals($form, $this->payFor->get3DFormData());
    }

    public function testCreate3DHash()
    {
        $this->payFor->prepare($this->order);
        $this->assertEquals($this->order->hash, $this->payFor->create3DHash());
    }

    public function testCheck3DHash()
    {
        $data = [
            "OrderId" => $this->order->id,
            "AuthCode" => "",
            "3DStatus" => "1",
            "ProcReturnCode" => "V033",
            "ResponseRnd" => "PF637404392360825218",
            "ResponseHash" => "ogupUOYY6vQ4+opqDqgLk3DLK7I=",
        ];

        $this->assertTrue($this->payFor->check3DHash($data));

        $data['3DStatus'] = '';
        $this->assertFalse($this->payFor->check3DHash($data));
    }
}

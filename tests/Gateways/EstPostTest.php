<?php

namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCardEstPos;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\EstPos;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class EstPostTest extends TestCase
{
    /**
     * @var EstPosAccount
     */
    private $account;
    /**
     * @var EstPos
     */
    private $pos;
    private $config;

    /**
     * @var CreditCardEstPos
     */
    private $card;
    private $order;
    /**
     * @var XmlEncoder
     */
    private $xmlDecoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createEstPosAccount(
            'akbank',
            'XXXXXXX',
            'XXXXXXX',
            'XXXXXXX',
            AbstractGateway::MODEL_3D_SECURE,
            'VnM5WZ3sGrPusmWP',
            EstPos::LANG_TR
        );

        $this->card = new CreditCardEstPos('5555444433332222', '21', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);

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
        ];

        $this->pos = PosFactory::createPosGateway($this->account);

        $this->pos->setTestMode(true);

        $this->xmlDecoder = new XmlEncoder();
    }

    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
    }

    public function testPrepare()
    {

        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());
    }

    public function testMapRecurringFrequency()
    {
        $this->assertEquals('M', $this->pos->mapRecurringFrequency('MONTH'));
        $this->assertEquals('M', $this->pos->mapRecurringFrequency('M'));
    }

    public function testGet3DFormWithCardData()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $form = [
            'gateway' => $this->config['banks'][$this->account->getBank()]['urls']['gateway']['test'],
            'inputs'  => [
                'clientid'                        => $this->account->getClientId(),
                'storetype'                       => $this->account->getModel(),
                'hash'                            => $this->pos->create3DHash(),
                'cardType'                        => $this->card->getCardCode(),
                'pan'                             => $this->card->getNumber(),
                'Ecom_Payment_Card_ExpDate_Month' => $this->card->getExpireMonth(),
                'Ecom_Payment_Card_ExpDate_Year'  => $this->card->getExpireYear(),
                'cv2'                             => $this->card->getCvv(),
                'firmaadi'                        => $this->order['name'],
                'Email'                           => $this->order['email'],
                'amount'                          => $this->order['amount'],
                'oid'                             => $this->order['id'],
                'okUrl'                           => $this->order['success_url'],
                'failUrl'                         => $this->order['fail_url'],
                'rnd'                             => $this->order['rand'],
                'lang'                            => $this->order['lang'],
                'currency'                        => 949,
            ],
        ];
        $this->assertEquals($form, $this->pos->get3DFormData());
    }

    public function testGet3DFormWithoutCardData()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY);

        $form = [
            'gateway' => $this->config['banks'][$this->account->getBank()]['urls']['gateway']['test'],
            'inputs'  => [
                'clientid'  => $this->account->getClientId(),
                'storetype' => $this->account->getModel(),
                'hash'      => $this->pos->create3DHash(),
                'firmaadi'  => $this->order['name'],
                'Email'     => $this->order['email'],
                'amount'    => $this->order['amount'],
                'oid'       => $this->order['id'],
                'okUrl'     => $this->order['success_url'],
                'failUrl'   => $this->order['fail_url'],
                'rnd'       => $this->order['rand'],
                'lang'      => $this->order['lang'],
                'currency'  => 949,
            ],
        ];
        $this->assertEquals($form, $this->pos->get3DFormData());
    }

    public function testGet3DHostFormData()
    {
        $account = AccountFactory::createEstPosAccount(
            'akbank',
            'XXXXXXX',
            'XXXXXXX',
            'XXXXXXX',
            AbstractGateway::MODEL_3D_HOST,
            'VnM5WZ3sGrPusmWP',
            EstPos::LANG_TR
        );
        $pos = PosFactory::createPosGateway($account);
        $pos->setTestMode(true);

        $pos->prepare($this->order, AbstractGateway::TX_PAY);

        $form = [
            'gateway' => $this->config['banks'][$account->getBank()]['urls']['gateway']['test'],
            'inputs'  => [
                'clientid'  => $account->getClientId(),
                'storetype' => $account->getModel(),
                'hash'      => $pos->create3DHash(),
                'firmaadi'  => $this->order['name'],
                'Email'     => $this->order['email'],
                'amount'    => $this->order['amount'],
                'oid'       => $this->order['id'],
                'okUrl'     => $this->order['success_url'],
                'failUrl'   => $this->order['fail_url'],
                'rnd'       => $this->order['rand'],
                'lang'      => $this->order['lang'],
                'currency'  => 949,
                'islemtipi'  => 'Auth',
                'taksit'    => $this->order['installment'],
            ],
        ];
        $this->assertEquals($form, $pos->get3DFormData());
    }

    public function testCheck3DHash()
    {
        $data = [
            "md"             => "478719:0373D10CFD8BDED34FA0546D27D5BE76F8BA4A947D1EC499102AE97B880EB1B9:4242:##400902568",
            "cavv"           => "BwAQAhIYRwEAABWGABhHEE6v5IU=",
            "AuthCode"       => "",
            "oid"            => "880",
            "mdStatus"       => "4",
            "eci"            => "06",
            "clientid"       => "400902568",
            "rnd"            => "hDx50d0cq7u1vbpWQMae",
            "ProcReturnCode" => "N7",
            "Response"       => "Declined",
            "HASH"           => "D+B5fFWXEWFqVSkwotyuTPUW800=",
            "HASHPARAMS"     => "clientid:oid:AuthCode:ProcReturnCode:Response:mdStatus:cavv:eci:md:rnd:",
            "HASHPARAMSVAL"  => "400902568880N7Declined4BwAQAhIYRwEAABWGABhHEE6v5IU=06478719:0373D10CFD8BDED34FA0546D27D5BE76F8BA4A947D1EC499102AE97B880EB1B9:4242:##400902568hDx50d0cq7u1vbpWQMae",
        ];

        $this->assertTrue($this->pos->check3DHash($data));

        $data['mdStatus'] = '';
        $this->assertFalse($this->pos->check3DHash($data));
    }

    public function testCreateRegularPaymentXML()
    {
        $order = [
            'id'          => '2020110828BC',
            'email'       => 'samp@iexample.com',
            'name'        => 'john doe',
            'user_id'     => '1535',
            'ip'          => '192.168.1.0',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
        ];


        $card = new CreditCardEstPos('5555444433332222', '22', '01', '123', 'ahmet');
        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_PAY, $card);

        $actualXML = $pos->createRegularPaymentXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRegularPaymentXMLData($pos->getOrder(), $pos->getCard(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
    }

    public function testCreateRegularPostXML()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_POST_PAY);

        $actualXML = $pos->createRegularPostXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRegularPostXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
    }

    public function testCreate3DPaymentXML()
    {

        $order = [
            'id'          => '2020110828BC',
            'email'       => 'samp@iexample.com',
            'name'        => 'john doe',
            'user_id'     => '1535',
            'ip'          => '192.168.1.0',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
        ];
        $responseData = [
            'md'   => '1',
            'xid'  => '100000005xid',
            'eci'  => '100000005eci',
            'cavv' => 'cavv',
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actualXML = $pos->create3DPaymentXML($responseData);
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSample3DPaymentXMLData($pos->getOrder(), $pos->getAccount(), $responseData);
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
    }

    public function testCreate3DPaymentXMLForRecurringOrder()
    {

        $order = [
            'id'                        => '2020110828BC',
            'email'                     => 'samp@iexample.com',
            'name'                      => 'john doe',
            'user_id'                   => '1535',
            'ip'                        => '192.168.1.0',
            'amount'                    => 100.01,
            'installment'               => '0',
            'currency'                  => 'TRY',
            'success_url'               => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'                  => 'http://localhost/finansbank-payfor/3d/response.php',
            'recurringFrequency'        => 3,
            'recurringFrequencyType'    => 'MONTH',
            'recurringInstallmentCount' => 4,
        ];

        $responseData = [
            'md'   => '1',
            'xid'  => '100000005xid',
            'eci'  => '100000005eci',
            'cavv' => 'cavv',
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actualXML = $pos->create3DPaymentXML($responseData);
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSample3DPaymentXMLData($pos->getOrder(), $pos->getAccount(), $responseData);
        $this->assertEquals($expectedData, $actualData);
        $this->assertEquals($expectedData['PbOrder'], $actualData['PbOrder']);
    }

    public function testCreateStatusXML()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_STATUS);

        $actualXML = $pos->createStatusXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleStatusXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
    }


    public function testCreateCancelXML()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_CANCEL);

        $actualXML = $pos->createCancelXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleCancelXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
    }

    public function testCreateRefundXML()
    {
        $order = [
            'id'     => '2020110828BC',
            'amount' => 50,
        ];

        /**
         * @var EstPos $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_REFUND);

        $actualXML = $pos->createRefundXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRefundXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData);
    }

    public function testMapCancelResponse()
    {
        $gatewayResponse = [
            'OrderId' => rand(),
            'GroupId' => rand(1, 100),
            'Response' => rand(1, 100),
            'AuthCode' => rand(1, 100),
            'HostRefNum' => rand(1, 100),
            'ProcReturnCode' => rand(1, 100),
            'TransId' => rand(1, 100),
            'Extra' => null,
            'ErrMsg' => null,
        ];
        $mapCancelResponseFunc = $this->getProtectedMethod('mapCancelResponse');
        $pos = PosFactory::createPosGateway($this->account);

        $canceledResult = $mapCancelResponseFunc->invokeArgs($pos, [json_decode(json_encode($gatewayResponse))]);
        $this->assertNotEmpty($canceledResult);

        $this->assertSame($gatewayResponse['OrderId'], $canceledResult->order_id);
        $this->assertSame($gatewayResponse['GroupId'], $canceledResult->group_id);
        $this->assertSame($gatewayResponse['Response'], $canceledResult->response);
        $this->assertSame($gatewayResponse['AuthCode'], $canceledResult->auth_code);
        $this->assertSame($gatewayResponse['HostRefNum'], $canceledResult->host_ref_num);
        $this->assertSame($gatewayResponse['ProcReturnCode'], $canceledResult->proc_return_code);
        $this->assertSame($gatewayResponse['TransId'], $canceledResult->trans_id);
        $this->assertSame(null, $canceledResult->error_code);
        $this->assertSame(null, $canceledResult->error_message);
        $this->assertSame('declined', $canceledResult->status);
    }

    /**
     * @param                  $order
     * @param CreditCardEstPos $card
     * @param EstPosAccount    $account
     *
     * @return array
     */
    private function getSampleRegularPaymentXMLData($order, $card, $account)
    {
        return [
            'Name'      => $account->getUsername(),
            'Password'  => $account->getPassword(),
            'ClientId'  => $account->getClientId(),
            'Type'      => 'Auth',
            'IPAddress' => $order->ip,
            'Email'     => $order->email,
            'OrderId'   => $order->id,
            'UserId'    => isset($order->user_id) ? $order->user_id : null,
            'Total'     => $order->amount,
            'Currency'  => $order->currency,
            'Taksit'    => $order->installment,
            'CardType'  => $card->getType(),
            'Number'    => $card->getNumber(),
            'Expires'   => $card->getExpirationDate(),
            'Cvv2Val'   => $card->getCvv(),
            'Mode'      => 'P',
            'GroupId'   => '',
            'TransId'   => '',
            'BillTo'    => [
                'Name' => $order->name ? $order->name : null,
            ],
        ];
    }

    /**
     * @param               $order
     * @param EstPosAccount $account
     *
     * @return array
     */
    private function getSampleRegularPostXMLData($order, $account)
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'Type'     => 'PostAuth',
            'OrderId'  => $order->id,
        ];
    }

    /**
     * @param               $order
     * @param EstPosAccount $account
     * @param array         $responseData
     *
     * @return array
     */
    private function getSample3DPaymentXMLData($order, $account, array $responseData)
    {
        $requestData = [
            'Name'                    => $account->getUsername(),
            'Password'                => $account->getPassword(),
            'ClientId'                => $account->getClientId(),
            'Type'                    => 'Auth',
            'IPAddress'               => $order->ip,
            'Email'                   => $order->email,
            'OrderId'                 => $order->id,
            'UserId'                  => isset($order->user_id) ? $order->user_id : null,
            'Total'                   => $order->amount,
            'Currency'                => $order->currency,
            'Taksit'                  => $order->installment,
            'Number'                  => $responseData['md'],
            'Expires'                 => '',
            'Cvv2Val'                 => '',
            'PayerTxnId'              => $responseData['xid'],
            'PayerSecurityLevel'      => $responseData['eci'],
            'PayerAuthenticationCode' => $responseData['cavv'],
            'CardholderPresentCode'   => '13',
            'Mode'                    => 'P',
            'GroupId'                 => '',
            'TransId'                 => '',
        ];
        if (isset($order->name)) {
            $requestData['BillTo'] = [
                'Name' => $order->name,
            ];
        }

        if (isset($order->recurringFrequency)) {
            $requestData['PbOrder'] = [
                'OrderType'              => 0,
                'OrderFrequencyInterval' => $order->recurringFrequency,
                'OrderFrequencyCycle'    => $order->recurringFrequencyType,
                'TotalNumberPayments'    => $order->recurringInstallmentCount,
            ];
        }

        return $requestData;
    }

    /**
     * @param               $order
     * @param EstPosAccount $account
     *
     * @return array
     */
    private function getSampleStatusXMLData($order, $account)
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order->id,
            'Extra'    => [
                'ORDERSTATUS' => 'QUERY',
            ],
        ];
    }

    /**
     * @param               $order
     * @param EstPosAccount $account
     *
     * @return array
     */
    private function getSampleCancelXMLData($order, $account)
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order->id,
            'Type'     => 'Void',
        ];
    }

    /**
     * @param               $order
     * @param EstPosAccount $account
     *
     * @return array
     */
    private function getSampleRefundXMLData($order, $account)
    {
        $data = [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order->id,
            'Type'     => 'Credit',
        ];

        if ($order->amount) {
            $data['Total'] = $order->amount;
        }

        return $data;
    }


    /**
     * @param string $name
     *
     * @return ReflectionMethod
     *
     * @throws ReflectionException
     */
    private static function getProtectedMethod(string $name)
    {
        $class = new ReflectionClass(EstPos::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}

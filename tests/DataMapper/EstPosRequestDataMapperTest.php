<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper;

use Mews\Pos\DataMapper\EstPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\EstPos;
use PHPUnit\Framework\TestCase;

/**
 * EstPosRequestDataMapperTest
 */
class EstPosRequestDataMapperTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $threeDAccount;

    /** @var EstPos */
    private $pos;

    /** @var AbstractCreditCard */
    private $card;

    /** @var EstPosRequestDataMapper */
    private $requestDataMapper;

    private $order;
    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->threeDAccount = AccountFactory::createEstPosAccount(
            'akbank',
            '700655000200',
            'ISBANKAPI',
            'ISBANK07',
            AbstractGateway::MODEL_3D_SECURE,
            'TRPS0200',
            EstPos::LANG_TR
        );

        $this->order = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
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

        $this->pos = PosFactory::createPosGateway($this->threeDAccount);
        $this->pos->setTestMode(true);
        $this->requestDataMapper = new EstPosRequestDataMapper();
        $this->card              = CreditCardFactory::create($this->pos, '5555444433332222', '22', '01', '123', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
    }

    /**
     * @return void
     */
    public function testMapRecurringFrequency()
    {
        $this->assertEquals('M', $this->requestDataMapper->mapRecurringFrequency('MONTH'));
        $this->assertEquals('M', $this->requestDataMapper->mapRecurringFrequency('M'));
    }

    /**
     * @return void
     */
    public function testMapCurrency()
    {
        $this->assertEquals('949', $this->requestDataMapper->mapCurrency('TRY'));
        $this->assertEquals('978', $this->requestDataMapper->mapCurrency('EUR'));
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePostAuthPaymentRequestData()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_POST_PAY);

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleNonSecurePaymentPostRequestData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData()
    {
        $order = $this->order;
        $pos   = $this->pos;
        $card  = $this->card;
        $pos->prepare($order, AbstractGateway::TX_PAY, $card);

        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($pos->getAccount(), $pos->getOrder(), 'Auth', $card);

        $expectedData = $this->getSampleNonSecurePaymentRequestData($pos->getAccount(), $pos->getOrder(), $pos->getCard());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DHashFor3DSecure()
    {
        $this->order['rand'] = 'rand';
        $pos                 = $this->pos;

        $expected = 'mJReAoQNtx0G+nWUGA/BHLx/+BI=';
        $pos->prepare($this->order, AbstractGateway::TX_PAY);
        $actual = $this->requestDataMapper->create3DHash($pos->getAccount(), $pos->getOrder(), 'Auth');
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DHashForNon3DSecure()
    {
        $this->order['rand'] = 'rand';

        $account  = AccountFactory::createEstPosAccount(
            'akbank',
            'XXXXXXX',
            'XXXXXXX',
            'XXXXXXX',
            AbstractGateway::MODEL_3D_PAY,
            'VnM5WZ3sGrPusmWP'
        );
        $pos      = PosFactory::createPosGateway($account);
        $expected = 'zW2HEQR/H0mpo1jrztIgmIPFFEU=';
        $pos->prepare($this->order, AbstractGateway::TX_PAY);
        $actual = $this->requestDataMapper->create3DHash($account, $pos->getOrder(), 'Auth');
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        $pos   = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_CANCEL);

        $actual = $this->requestDataMapper->createCancelRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleCancelXMLData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateHistoryRequestData()
    {
        $order = [
            'order_id' => '2020110828BC',
        ];
        $pos   = $this->pos;

        $actual = $this->requestDataMapper->createHistoryRequestData($pos->getAccount(), (object) [], $order);

        $expectedData = $this->getSampleHistoryRequestData($pos->getAccount(), $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DPaymentRequestData()
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

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actual = $this->requestDataMapper->create3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), 'Auth', $responseData);

        $expectedData = $this->getSample3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), $responseData);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DPaymentRequestDataRecurringOrder()
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

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actual = $this->requestDataMapper->create3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), 'Auth', $responseData);

        $expectedData = $this->getSample3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), $responseData);
        $this->assertEquals($expectedData, $actual);
    }


    /**
     * @return void
     */
    public function testGet3DFormData()
    {
        $account = $this->threeDAccount;
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY);
        $txType = 'Auth';
        $hash       = $this->requestDataMapper->create3DHash($account, $this->pos->getOrder(), $txType);
        $card       = $this->card;
        $gatewayURL = $this->config['banks'][$this->threeDAccount->getBank()]['urls']['gateway']['test'];

        $inputs = [
            'clientid'  => $account->getClientId(),
            'storetype' => $account->getModel(),
            'hash'      => $hash,
            'firmaadi'  => $this->order['name'],
            'Email'     => $this->order['email'],
            'amount'    => $this->order['amount'],
            'oid'       => $this->order['id'],
            'okUrl'     => $this->order['success_url'],
            'failUrl'   => $this->order['fail_url'],
            'rnd'       => $this->order['rand'],
            'lang'      => 'tr',
            'currency'  => 949,
        ];
        $form   = [
            'gateway' => $gatewayURL,
            'inputs'  => $inputs,
        ];
        //test without card
        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->pos->getAccount(),
            $this->pos->getOrder(),
            'Auth',
            $gatewayURL
        ));

        //test with card
        if ($card) {
            $form['inputs']['cardType']                        = '1';
            $form['inputs']['pan']                             = $card->getNumber();
            $form['inputs']['Ecom_Payment_Card_ExpDate_Month'] = '01';
            $form['inputs']['Ecom_Payment_Card_ExpDate_Year']  = '22';
            $form['inputs']['cv2']                             = $card->getCvv();
        }

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->pos->getAccount(),
            $this->pos->getOrder(),
            'Auth',
            $gatewayURL,
            $card
        ));
    }

    /**
     * todo
     * @return void
     */
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

        /** @var EstPos $pos */
        $pos = PosFactory::createPosGateway($account);
        $pos->setTestMode(true);
        $pos->prepare($this->order, AbstractGateway::TX_PAY);
        $order      = $pos->getOrder();
        $hash       = $this->requestDataMapper->create3DHash($account, $order, 'Auth');
        $gatewayURL = $this->config['banks'][$this->threeDAccount->getBank()]['urls']['gateway_3d_host']['test'];
        $inputs     = [
            'clientid'  => $account->getClientId(),
            'storetype' => $account->getModel(),
            'hash'      => $hash,
            'firmaadi'  => $this->order['name'],
            'Email'     => $this->order['email'],
            'amount'    => $this->order['amount'],
            'oid'       => $this->order['id'],
            'okUrl'     => $this->order['success_url'],
            'failUrl'   => $this->order['fail_url'],
            'rnd'       => $this->order['rand'],
            'lang'      => 'tr',
            'currency'  => '949',
            'islemtipi'  => 'Auth',
            'taksit'    => $this->order['installment'],
        ];
        $form       = [
            'gateway' => $gatewayURL,
            'inputs'  => $inputs,
        ];

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $pos->getAccount(),
            $pos->getOrder(),
            'Auth',
            $gatewayURL
        ));
    }

    /**
     * @return void
     */
    public function testCreateStatusRequestData()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_STATUS);

        $actualData = $this->requestDataMapper->createStatusRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleStatusRequestData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * todo
     * @return void
     */
    public function testCreateRefundRequestData()
    {
        $order = [
            'id'       => '2020110828BC',
            'amount'   => 50,
            'currency' => 'TRY',
        ];

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_REFUND);

        $actual = $this->requestDataMapper->createRefundRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleRefundXMLData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param array              $responseData
     *
     * @return array
     */
    private function getSample3DPaymentRequestData(AbstractPosAccount $account, $order, array $responseData): array
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
            'Total'                   => 100.01,
            'Currency'                => '949',
            'Taksit'                  => '0',
            'Number'                  => $responseData['md'],
            'PayerTxnId'              => $responseData['xid'],
            'PayerSecurityLevel'      => $responseData['eci'],
            'PayerAuthenticationCode' => $responseData['cavv'],
            'Mode'                    => 'P',
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
                'OrderFrequencyCycle'    => 'M',
                'TotalNumberPayments'    => $order->recurringInstallmentCount,
            ];
        }

        return $requestData;
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $account, $order): array
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
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param AbstractCreditCard $card
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $account, $order, AbstractCreditCard $card): array
    {
        return [
            'Name'      => $account->getUsername(),
            'Password'  => $account->getPassword(),
            'ClientId'  => $account->getClientId(),
            'Type'      => 'Auth',
            'IPAddress' => $order->ip,
            'Email'     => $order->email,
            'OrderId'   => $order->id,
            'UserId'    => $order->user_id ?? null,
            'Total'     => '100.25',
            'Currency'  => '949',
            'Taksit'    => '0',
            'Number'    => $card->getNumber(),
            'Expires'   => '01/22',
            'Cvv2Val'   => $card->getCvv(),
            'Mode'      => 'P',
            'BillTo'    => [
                'Name' => $order->name ?: null,
            ],
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $account, $order): array
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
     * @param AbstractPosAccount $account
     * @param                    $order
     *
     * @return array
     */
    private function getSampleStatusRequestData(AbstractPosAccount $account, $order): array
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
     * @param AbstractPosAccount $account
     * @param                    $order
     *
     * @return array
     */
    private function getSampleRefundXMLData(AbstractPosAccount $account, $order): array
    {
        $data = [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order->id,
            'Currency' => 949,
            'Type'     => 'Credit',
        ];

        if ($order->amount) {
            $data['Total'] = $order->amount;
        }

        return $data;
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $customQueryData
     *
     * @return array
     */
    private function getSampleHistoryRequestData(AbstractPosAccount $account, $customQueryData): array
    {
        $requestData = [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $customQueryData['order_id'],
            'Extra'    => [
                'ORDERHISTORY' => 'QUERY',
            ],
        ];

        return $requestData;
    }
}

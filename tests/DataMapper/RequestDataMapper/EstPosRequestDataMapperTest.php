<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper\RequestDataMapper;

use Mews\Pos\DataMapper\RequestDataMapper\EstPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * EstPosRequestDataMapperTest
 */
class EstPosRequestDataMapperTest extends TestCase
{
    private EstPosAccount $account;

    private CreditCardInterface $card;

    private EstPosRequestDataMapper $requestDataMapper;

    private $order;

    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../../config/pos_test.php';

        $this->account = AccountFactory::createEstPosAccount(
            'akbank',
            '700655000200',
            'ISBANKAPI',
            'ISBANK07',
            PosInterface::MODEL_3D_SECURE,
            'TRPS0200'
        );

        $this->order = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'rand'        => 'rand',
        ];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos = PosFactory::createPosGateway($this->account, $this->config, $dispatcher);

        $this->requestDataMapper = new EstPosRequestDataMapper($dispatcher, CryptFactory::createGatewayCrypt(EstPos::class, new NullLogger()));
        $this->card              = CreditCardFactory::create($pos, '5555444433332222', '22', '01', '123', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
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
        $this->assertEquals('949', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertEquals('978', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_EUR));
    }

    /**
     * @param string|int|null $installment
     * @param string|int      $expected
     *
     * @testWith ["0", ""]
     *           ["1", ""]
     *           ["2", "2"]
     *           [2, "2"]
     *
     * @return void
     */
    public function testMapInstallment($installment, $expected)
    {
        $actual = $this->requestDataMapper->mapInstallment($installment);
        $this->assertSame($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePostAuthPaymentRequestData()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $expectedData = $this->getSampleNonSecurePaymentPostRequestData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData()
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $this->order, PosInterface::TX_PAY, $this->card);

        $expectedData = $this->getSampleNonSecurePaymentRequestData($this->account, $this->order, $this->card);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData()
    {
        $order = [
            'id' => '2020110828BC',
        ];

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $expectedData = $this->getSampleCancelXMLData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRecurringOrderRequestData()
    {
        $order = [
            'id' => '2020110828BC',
            'recurringOrderInstallmentNumber' => '2',
        ];

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $expectedData = $this->getSampleRecurringOrderCancelXMLData($this->account, $order);
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

        $actual = $this->requestDataMapper->createHistoryRequestData($this->account, [], $order);

        $expectedData = $this->getSampleHistoryRequestData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider threeDPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(AbstractPosAccount $account, array $order, string $txType, array $responseData, array $expected)
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($account, $order, $txType, $responseData);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testGet3DFormData()
    {
        $txType = PosInterface::TX_PAY;
        $card       = $this->card;
        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d'];

        $inputs = [
            'clientid'    => $this->account->getClientId(),
            'storetype'   => PosInterface::MODEL_3D_SECURE,
            'hash'        => 'TN+2/D8lijFd+5zAUar6SH6EiRY=',
            'amount'      => $this->order['amount'],
            'oid'         => $this->order['id'],
            'okUrl'       => $this->order['success_url'],
            'failUrl'     => $this->order['fail_url'],
            'callbackUrl' => $this->order['fail_url'],
            'rnd'         => $this->order['rand'],
            'lang'        => 'tr',
            'currency'    => 949,
            'islemtipi'   => 'Auth',
            'taksit'      => '',
        ];
        $form   = [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
        //test without card
        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->account,
            $this->order,
            PosInterface::MODEL_3D_SECURE,
            $txType,
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
            $this->account,
            $this->order,
            PosInterface::MODEL_3D_SECURE,
            $txType,
            $gatewayURL,
            $card
        ));
    }

    /**
     * @return void
     */
    public function testGet3DHostFormData()
    {
        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d'];
        $inputs     = [
            'clientid'    => $this->account->getClientId(),
            'storetype'   => '3d_host',
            'hash'        => 'TN+2/D8lijFd+5zAUar6SH6EiRY=',
            'amount'      => $this->order['amount'],
            'oid'         => $this->order['id'],
            'okUrl'       => $this->order['success_url'],
            'failUrl'     => $this->order['fail_url'],
            'callbackUrl' => $this->order['fail_url'],
            'rnd'         => $this->order['rand'],
            'lang'        => 'tr',
            'currency'    => '949',
            'islemtipi'   => 'Auth',
            'taksit'      => '',
        ];
        $form       = [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->account,
            $this->order,
            PosInterface::MODEL_3D_HOST,
            PosInterface::TX_PAY,
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

        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $expectedData = $this->getSampleStatusRequestData($this->account, $order);
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateRecurringStatusRequestData()
    {
        $order = [
            'recurringId' => '2020110828BC',
        ];

        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $expectedData = $this->getSampleRecurringStatusRequestData($this->account, $order);
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData()
    {
        $order = [
            'id'       => '2020110828BC',
            'amount'   => 50,
            'currency' => PosInterface::CURRENCY_TRY,
        ];

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $expectedData = $this->getSampleRefundXMLData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }


    public static function threeDPaymentRequestDataDataProvider(): \Generator
    {
        $account = AccountFactory::createEstPosAccount(
            'akbank',
            '700655000200',
            'ISBANKAPI',
            'ISBANK07',
            PosInterface::MODEL_3D_SECURE,
            'TRPS0200'
        );

        $order = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'rand'        => 'rand',
        ];

        $responseData = [
            'md'   => '1',
            'xid'  => '100000005xid',
            'eci'  => '100000005eci',
            'cavv' => 'cavv',
        ];

        yield [
            'account'      => $account,
            'order'        => $order,
            'txType'       => PosInterface::TX_PAY,
            'responseData' => $responseData,
            'expected'     => [
                'Name'                    => 'ISBANKAPI',
                'Password'                => 'ISBANK07',
                'ClientId'                => '700655000200',
                'Type'                    => 'Auth',
                'IPAddress'               => '127.0.0.1',
                'OrderId'                 => 'order222',
                'Total'                   => '100.25',
                'Currency'                => '949',
                'Taksit'                  => '',
                'Number'                  => '1',
                'PayerTxnId'              => '100000005xid',
                'PayerSecurityLevel'      => '100000005eci',
                'PayerAuthenticationCode' => 'cavv',
                'Mode'                    => 'P',
            ],
        ];

        $order['recurring']   = [
            'frequency'     => 2,
            'frequencyType' => 'MONTH',
            'installment'   => 3,
        ];
        $order['installment'] = 0;

        yield 'recurring_order' => [
            'account'      => $account,
            'order'        => $order,
            'txType'       => PosInterface::TX_PAY,
            'responseData' => $responseData,
            'expected'     => [
                'Name'                    => 'ISBANKAPI',
                'Password'                => 'ISBANK07',
                'ClientId'                => '700655000200',
                'Type'                    => 'Auth',
                'IPAddress'               => '127.0.0.1',
                'OrderId'                 => 'order222',
                'Total'                   => '100.25',
                'Currency'                => '949',
                'Taksit'                  => '',
                'Number'                  => '1',
                'PayerTxnId'              => '100000005xid',
                'PayerSecurityLevel'      => '100000005eci',
                'PayerAuthenticationCode' => 'cavv',
                'Mode'                    => 'P',
                'PbOrder'                 => [
                    'OrderType'              => '0',
                    'OrderFrequencyInterval' => '2',
                    'OrderFrequencyCycle'    => 'M',
                    'TotalNumberPayments'    => '3',
                ],
            ],
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $account, array $order): array
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order['id'],
            'Type'     => 'Void',
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleRecurringOrderCancelXMLData(AbstractPosAccount $account, array $order): array
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'Extra'  => [
                'RECORDTYPE' => 'Order',
                'RECURRINGOPERATION' => 'Cancel',
                'RECORDID' => $order['id'] . '-' . $order['recurringOrderInstallmentNumber'],
            ],
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     * @param CreditCardInterface $card
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, CreditCardInterface $card): array
    {
        return [
            'Name'      => $account->getUsername(),
            'Password'  => $account->getPassword(),
            'ClientId'  => $account->getClientId(),
            'Type'      => 'Auth',
            'IPAddress' => $order['ip'],
            'OrderId'   => $order['id'],
            'Total'     => '100.25',
            'Currency'  => '949',
            'Taksit'    => '',
            'Number'    => $card->getNumber(),
            'Expires'   => '01/22',
            'Cvv2Val'   => $card->getCvv(),
            'Mode'      => 'P',
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $account, array $order): array
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'Type'     => 'PostAuth',
            'OrderId'  => $order['id'],
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleStatusRequestData(AbstractPosAccount $account, array $order): array
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order['id'],
            'Extra'    => [
                'ORDERSTATUS' => 'QUERY',
            ],
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleRecurringStatusRequestData(AbstractPosAccount $account, array $order): array
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'Extra'    => [
                'ORDERSTATUS' => 'QUERY',
                'RECURRINGID' => $order['recurringId'],
            ],
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleRefundXMLData(AbstractPosAccount $account, array $order): array
    {
        $data = [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order['id'],
            'Currency' => 949,
            'Type'     => 'Credit',
        ];

        if ($order['amount']) {
            $data['Total'] = $order['amount'];
        }

        return $data;
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $customQueryData
     *
     * @return array
     */
    private function getSampleHistoryRequestData(AbstractPosAccount $account, array $customQueryData): array
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $customQueryData['order_id'],
            'Extra'    => [
                'ORDERHISTORY' => 'QUERY',
            ],
        ];
    }
}

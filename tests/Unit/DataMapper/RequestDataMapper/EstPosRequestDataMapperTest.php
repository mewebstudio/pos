<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\EstPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\EstPosRequestDataMapper
 */
class EstPosRequestDataMapperTest extends TestCase
{
    private EstPosAccount $account;

    private CreditCardInterface $card;

    private EstPosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    private array $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createEstPosAccount(
            'akbank',
            '700655000200',
            'ISBANKAPI',
            'ISBANK07',
            PosInterface::MODEL_3D_SECURE,
            'TRPS0200'
        );

        $this->order      = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->crypt      = $this->createMock(CryptInterface::class);

        $this->requestDataMapper = new EstPosRequestDataMapper($this->dispatcher, $this->crypt);
        $this->card              = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    /**
     * @testWith ["MONTH", "M"]
     *            ["M", "M"]
     */
    public function testMapRecurringFrequency(string $frequency, string $expected): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('mapRecurringFrequency');
        $method->setAccessible(true);
        $this->assertSame($expected, $method->invokeArgs($this->requestDataMapper, [$frequency]));
    }

    /**
     * @return void
     */
    public function testMapCurrency(): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('mapCurrency');
        $method->setAccessible(true);
        $this->assertSame('949', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_TRY]));
        $this->assertSame('978', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_EUR]));
    }

    /**
     * @testWith ["pay", "Auth"]
     * ["pre", "PreAuth"]
     */
    public function testMapTxType(string $txType, string $expected): void
    {
        $actual = $this->requestDataMapper->mapTxType($txType);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["Auth"]
     */
    public function testMapTxTypeException(string $txType): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->requestDataMapper->mapTxType($txType);
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
    public function testMapInstallment($installment, $expected): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('mapInstallment');
        $method->setAccessible(true);
        $this->assertSame($expected, $method->invokeArgs($this->requestDataMapper, [$installment]));
    }

    /**
     * @dataProvider postAuthRequestDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData(): void
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $this->order, PosInterface::TX_TYPE_PAY_AUTH, $this->card);

        $expectedData = $this->getSampleNonSecurePaymentRequestData($this->account, $this->order, $this->card);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData(): void
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
    public function testCreateCancelRecurringOrderRequestData(): void
    {
        $order = [
            'id'                              => '2020110828BC',
            'recurringOrderInstallmentNumber' => '2',
        ];

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $expectedData = $this->getSampleRecurringOrderCancelXMLData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider orderHistoryRequestDataProvider
     */
    public function testCreateOrderHistoryRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider threeDPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData, array $expected): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($posAccount, $order, $txType, $responseData);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(
        array  $order,
        string $gatewayURL,
        string $txType,
        string $paymentModel,
        bool   $isWithCard,
        array  $expected
    ): void
    {
        $this->crypt->expects(self::once())
            ->method('create3DHash')
            ->willReturn($expected['inputs']['hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['inputs']['rnd']);

        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with($this->callback(function ($dispatchedEvent) use ($txType, $paymentModel) {
                return $dispatchedEvent instanceof Before3DFormHashCalculatedEvent
                    && EstPos::class === $dispatchedEvent->getGatewayClass()
                    && $txType === $dispatchedEvent->getTxType()
                    && $paymentModel === $dispatchedEvent->getPaymentModel()
                    && count($dispatchedEvent->getFormInputs()) > 3
                    ;
            }));

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayURL,
            $isWithCard ? $this->card : null
        );

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreateStatusRequestData(): void
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
    public function testCreateRecurringStatusRequestData(): void
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
    public function testCreateRefundRequestData(): void
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
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
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
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
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

    public static function threeDFormDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];

        return [
            'without_card' => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'clientid'  => '700655000200',
                        'storetype' => '3d',
                        'amount'    => '100.25',
                        'oid'       => 'order222',
                        'okUrl'     => 'https://domain.com/success',
                        'failUrl'   => 'https://domain.com/fail_url',
                        'rnd'       => 'rand-21212',
                        'lang'      => 'tr',
                        'currency'  => '949',
                        'taksit'    => '',
                        'hash'      => 'TN+2/D8lijFd+5zAUar6SH6EiRY=',
                        'islemtipi' => 'Auth',
                    ],
                ],
            ],
            'with_card'    => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => true,
                'expected'     => [
                    'gateway' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'clientid'                        => '700655000200',
                        'storetype'                       => '3d',
                        'amount'                          => '100.25',
                        'oid'                             => 'order222',
                        'okUrl'                           => 'https://domain.com/success',
                        'failUrl'                         => 'https://domain.com/fail_url',
                        'rnd'                             => 'rand-21212',
                        'lang'                            => 'tr',
                        'currency'                        => '949',
                        'taksit'                          => '',
                        'islemtipi'                       => 'Auth',
                        'hash'                            => 'TN+2/D8lijFd+5zAUar6SH6EiRY=',
                        'pan'                             => '5555444433332222',
                        'Ecom_Payment_Card_ExpDate_Month' => '01',
                        'Ecom_Payment_Card_ExpDate_Year'  => '22',
                        'cv2'                             => '123',
                    ],
                ],
            ],
            '3d_host'      => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'clientid'  => '700655000200',
                        'storetype' => '3d_host',
                        'amount'    => '100.25',
                        'oid'       => 'order222',
                        'okUrl'     => 'https://domain.com/success',
                        'failUrl'   => 'https://domain.com/fail_url',
                        'rnd'       => 'rand-21212',
                        'lang'      => 'tr',
                        'currency'  => '949',
                        'taksit'    => '',
                        'islemtipi' => 'Auth',
                        'hash'      => 'TN+2/D8lijFd+5zAUar6SH6EiRY=',
                    ],
                ],
            ],
        ];
    }

    public static function postAuthRequestDataProvider(): array
    {
        return [
            'without_amount'       => [
                'order'    => [
                    'id' => '2020110828BC',
                ],
                'expected' => [
                    'Name'     => 'ISBANKAPI',
                    'Password' => 'ISBANK07',
                    'ClientId' => '700655000200',
                    'Type'     => 'PostAuth',
                    'OrderId'  => '2020110828BC',
                    'Total'    => null,
                ],
            ],
            'with_amount'          => [
                'order'    => [
                    'id'     => '2020110828BC',
                    'amount' => 1.0,
                ],
                'expected' => [
                    'Name'     => 'ISBANKAPI',
                    'Password' => 'ISBANK07',
                    'ClientId' => '700655000200',
                    'Type'     => 'PostAuth',
                    'OrderId'  => '2020110828BC',
                    'Total'    => 1.0,
                ],
            ],
            'with_pre_auth_amount' => [
                'order'    => [
                    'id'              => '2020110828BC',
                    'amount'          => 1.1,
                    'pre_auth_amount' => 1.0,
                ],
                'expected' => [
                    'Name'     => 'ISBANKAPI',
                    'Password' => 'ISBANK07',
                    'ClientId' => '700655000200',
                    'Type'     => 'PostAuth',
                    'OrderId'  => '2020110828BC',
                    'Total'    => 1.1,
                    'Extra'    => [
                        'PREAMT' => 1.0,
                    ],
                ],
            ],
        ];
    }

    public static function orderHistoryRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id' => '2020110828BC',
                ],
                'expected' => [
                    'Name'     => 'ISBANKAPI',
                    'Password' => 'ISBANK07',
                    'ClientId' => '700655000200',
                    'OrderId'  => '2020110828BC',
                    'Extra'    => [
                        'ORDERHISTORY' => 'QUERY',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     * @param array              $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'Name'     => $posAccount->getUsername(),
            'Password' => $posAccount->getPassword(),
            'ClientId' => $posAccount->getClientId(),
            'OrderId'  => $order['id'],
            'Type'     => 'Void',
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     * @param array              $order
     *
     * @return array
     */
    private function getSampleRecurringOrderCancelXMLData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'Name'     => $posAccount->getUsername(),
            'Password' => $posAccount->getPassword(),
            'ClientId' => $posAccount->getClientId(),
            'Extra'    => [
                'RECORDTYPE'         => 'Order',
                'RECURRINGOPERATION' => 'Cancel',
                'RECORDID'           => $order['id'].'-'.$order['recurringOrderInstallmentNumber'],
            ],
        ];
    }

    /**
     * @param AbstractPosAccount  $posAccount
     * @param array               $order
     * @param CreditCardInterface $creditCard
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, CreditCardInterface $creditCard): array
    {
        return [
            'Name'      => $posAccount->getUsername(),
            'Password'  => $posAccount->getPassword(),
            'ClientId'  => $posAccount->getClientId(),
            'Type'      => 'Auth',
            'IPAddress' => $order['ip'],
            'OrderId'   => $order['id'],
            'Total'     => '100.25',
            'Currency'  => '949',
            'Taksit'    => '',
            'Number'    => $creditCard->getNumber(),
            'Expires'   => '01/22',
            'Cvv2Val'   => $creditCard->getCvv(),
            'Mode'      => 'P',
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     * @param array              $order
     *
     * @return array
     */
    private function getSampleStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'Name'     => $posAccount->getUsername(),
            'Password' => $posAccount->getPassword(),
            'ClientId' => $posAccount->getClientId(),
            'OrderId'  => $order['id'],
            'Extra'    => [
                'ORDERSTATUS' => 'QUERY',
            ],
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     * @param array              $order
     *
     * @return array
     */
    private function getSampleRecurringStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'Name'     => $posAccount->getUsername(),
            'Password' => $posAccount->getPassword(),
            'ClientId' => $posAccount->getClientId(),
            'Extra'    => [
                'ORDERSTATUS' => 'QUERY',
                'RECURRINGID' => $order['recurringId'],
            ],
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     * @param array              $order
     *
     * @return array
     */
    private function getSampleRefundXMLData(AbstractPosAccount $posAccount, array $order): array
    {
        $data = [
            'Name'     => $posAccount->getUsername(),
            'Password' => $posAccount->getPassword(),
            'ClientId' => $posAccount->getClientId(),
            'OrderId'  => $order['id'],
            'Currency' => 949,
            'Type'     => 'Credit',
        ];

        if ($order['amount']) {
            $data['Total'] = $order['amount'];
        }

        return $data;
    }
}

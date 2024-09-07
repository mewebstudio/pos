<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use InvalidArgumentException;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetRequestDataMapper;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\PosNetRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class PosNetRequestDataMapperTest extends TestCase
{
    private CreditCardInterface $card;

    private PosNetRequestDataMapper $requestDataMapper;

    private array $order;

    private PosNetAccount $account;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706598320',
            '67005551',
            '27426',
            PosInterface::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $this->order = [
            'id'          => 'TST_190620093100_024',
            'amount'      => '1.75',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];

        $this->dispatcher        = $this->createMock(EventDispatcherInterface::class);
        $crypt                   = CryptFactory::createGatewayCrypt(PosNet::class, new NullLogger());
        $this->requestDataMapper = new PosNetRequestDataMapper($this->dispatcher, $crypt);
        $this->card              = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet');
    }

    /**
     * @testWith ["pay", "Sale"]
     * ["pre", "Auth"]
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
     * @return void
     */
    public function testMapCurrency(): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('mapCurrency');
        $method->setAccessible(true);
        $this->assertSame('TL', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_TRY]));
        $this->assertSame('EU', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_EUR]));
    }

    /**
     * @return void
     */
    public function testFormatAmount(): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('formatAmount');
        $method->setAccessible(true);
        $this->assertSame(100000, $method->invokeArgs($this->requestDataMapper, [1000]));
        $this->assertSame(100000, $method->invokeArgs($this->requestDataMapper, [1000.00]));
        $this->assertSame(100001, $method->invokeArgs($this->requestDataMapper, [1000.01]));
    }

    /**
     * @param string|int|null $installment
     * @param string|int      $expected
     *
     * @testWith ["0", "00"]
     *           ["1", "00"]
     *           ["2", "02"]
     *           ["12", "12"]
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
     * @return void
     */
    public function testMapOrderIdToPrefixedOrderId(): void
    {
        $this->assertSame('TDSC00000000000000000010', PosNetRequestDataMapper::mapOrderIdToPrefixedOrderId(10, PosInterface::MODEL_3D_SECURE));
        $this->assertSame('000000000000000000000010', PosNetRequestDataMapper::mapOrderIdToPrefixedOrderId(10, PosInterface::MODEL_3D_PAY));
        $this->assertSame('000000000000000000000010', PosNetRequestDataMapper::mapOrderIdToPrefixedOrderId(10, PosInterface::MODEL_NON_SECURE));
    }

    /**
     * @return void
     */
    public function testFormatOrderId(): void
    {
        $this->assertSame('0010', PosNetRequestDataMapper::formatOrderId(10, 4));
        $this->assertSame('12345', PosNetRequestDataMapper::formatOrderId(12345, 5));
        $this->assertSame('123456789012345566fm', PosNetRequestDataMapper::formatOrderId('123456789012345566fm'));
    }

    /**
     * @return void
     */
    public function testFormatOrderIdFail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PosNetRequestDataMapper::formatOrderId('123456789012345566fml');
    }

    /**
     * @dataProvider nonSecurePaymentPostRequestDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, string $txType, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, $txType, $this->card);

        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider cancelDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(array $order, string $txType, array $responseData, array $expected): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $responseData);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider threeDEnrollmentCheckRequestDataProvider
     */
    public function testCreate3DEnrollmentCheckRequestData(array $order, string $txType, array $expectedData): void
    {
        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $order, $txType, $this->card);
        $this->assertEquals($expectedData, $actual);
    }


    /**
     * @return void
     */
    public function testCreate3DEnrollmentCheckRequestDataFailTooLongOrderId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->order['id'] = 'd32458293945098y439244343';
        $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $this->order, PosInterface::TX_TYPE_PAY_AUTH, $this->card);
    }

    /**
     * @dataProvider resolveMerchantDataDataProvider
     */
    public function testCreate3DResolveMerchantRequestData(array $order, array $responseData, array $expectedData): void
    {
        $actualData = $this->requestDataMapper->create3DResolveMerchantRequestData($this->account, $order, $responseData);

        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @dataProvider threeDFormDataDataProvider
     */
    public function testCreate3DFormData(array $ooTxSuccessData, array $order, string $gatewayURL, array $expected): void
    {
        $this->dispatcher->expects(self::never())
            ->method('dispatch');

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            PosInterface::MODEL_3D_SECURE,
            '',
            $gatewayURL,
            null,
            $ooTxSuccessData['oosRequestDataResponse']
        );

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider statusRequestDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expectedData): void
    {
        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public function testCreateHistoryRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createHistoryRequestData($this->account);
    }

    public function testCreateOrderHistoryRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createOrderHistoryRequestData($this->account, []);
    }

    public static function threeDFormDataDataProvider(): array
    {
        return [
            [
                'enrollment_check_response' => [
                    'approved'               => '1', //1:Başarılı
                    'respCode'               => '',
                    'respText'               => '',
                    'oosRequestDataResponse' => [
                        'data1' => 'AEFE78BFC852867FF57078B723E284D1BD52EED8264C6CBD110A1A9EA5EAA7533D1A82EFD614032D686C507738FDCDD2EDD00B22DEFEFE0795DC4674C16C02EBBFEC9DF0F495D5E23BE487A798BF8293C7C1D517D9600C96CBFD8816C9D8F8257442906CB9B10D8F1AABFBBD24AA6FB0E5533CDE67B0D9EA5ED621B91BF6991D5362182302B781241B56E47BAE1E86BC3D5AE7606212126A4E97AFC2',
                        'data2' => '69D04861340091B7014B15158CA3C83413031B406F08B3792A0114C9958E6F0F216966C5EE32EAEEC7158BFF59DFCB77E20CD625',
                        'sign'  => '9998F61E1D0C0FB6EC5203A748124F30',
                    ],
                ],
                'order'                     => [
                    'id'          => 'TST_190620093100_024',
                    'amount'      => '1.75',
                    'success_url' => 'https://domain.com/success',
                ],
                'gateway_url'               => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService',
                'expected'                  => [
                    'gateway' => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService',
                    'method'  => 'POST',
                    'inputs'  => [
                        'posnetData'        => 'AEFE78BFC852867FF57078B723E284D1BD52EED8264C6CBD110A1A9EA5EAA7533D1A82EFD614032D686C507738FDCDD2EDD00B22DEFEFE0795DC4674C16C02EBBFEC9DF0F495D5E23BE487A798BF8293C7C1D517D9600C96CBFD8816C9D8F8257442906CB9B10D8F1AABFBBD24AA6FB0E5533CDE67B0D9EA5ED621B91BF6991D5362182302B781241B56E47BAE1E86BC3D5AE7606212126A4E97AFC2',
                        'posnetData2'       => '69D04861340091B7014B15158CA3C83413031B406F08B3792A0114C9958E6F0F216966C5EE32EAEEC7158BFF59DFCB77E20CD625',
                        'mid'               => '6706598320',
                        'posnetID'          => '27426',
                        'digest'            => '9998F61E1D0C0FB6EC5203A748124F30',
                        'merchantReturnURL' => 'https://domain.com/success',
                        'url'               => '',
                        'lang'              => 'tr',
                    ],
                ],
            ],
        ];
    }

    public static function create3DPaymentRequestDataDataProvider(): array
    {
        return [
            'test1' => [
                'order'        => [
                    'id'          => '2020110828BC',
                    'amount'      => 100.01,
                    'installment' => '0',
                    'currency'    => PosInterface::CURRENCY_TRY,
                ],
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'responseData' => [
                    'BankPacket'     => 'F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                    'MerchantPacket' => 'E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                    'Sign'           => '9998F61E1D0C0FB6EC5203A748124F30',
                ],
                'expected'     => [
                    'mid'         => '6706598320',
                    'tid'         => '67005551',
                    'oosTranData' => [
                        'bankData'     => 'F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                        'merchantData' => 'E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                        'sign'         => '9998F61E1D0C0FB6EC5203A748124F30',
                        'wpAmount'     => 0,
                        'mac'          => 'oE7zwV87uOc2DFpGPlr4jQRQ0z9LsxGw56c7vaiZkTo=',
                    ],
                ],
            ],
        ];
    }

    public static function cancelDataProvider(): array
    {
        return [
            'with_order_id'    => [
                'order'    => [
                    'id'            => '2020110828BC',
                    'payment_model' => PosInterface::MODEL_3D_SECURE,
                    'amount'        => 50,
                    'currency'      => PosInterface::CURRENCY_TRY,
                ],
                'expected' => [
                    'mid'              => '6706598320',
                    'tid'              => '67005551',
                    'tranDateRequired' => '1',
                    'reverse'          => [
                        'transaction' => 'sale',
                        'orderID'     => 'TDSC000000002020110828BC',
                    ],
                ],
            ],
            'with_ref_ret_num' => [
                'order'    => [
                    'ref_ret_num'   => '019676067890000191',
                    'payment_model' => PosInterface::MODEL_3D_SECURE,
                    'amount'        => 50,
                    'currency'      => PosInterface::CURRENCY_TRY,
                ],
                'expected' => [
                    'mid'              => '6706598320',
                    'tid'              => '67005551',
                    'tranDateRequired' => '1',
                    'reverse'          => [
                        'transaction' => 'sale',
                        'hostLogKey'  => '019676067890000191',
                    ],
                ],
            ],
        ];
    }

    public static function nonSecurePaymentRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'          => 'TST_190620093100_024',
                    'amount'      => '1.75',
                    'installment' => 0,
                ],
                'tx_type'  => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => [
                    'mid'              => '6706598320',
                    'tid'              => '67005551',
                    'tranDateRequired' => '1',
                    'sale'             => [
                        'orderID'      => 'TST_190620093100_024',
                        'installment'  => 0,
                        'amount'       => 175,
                        'currencyCode' => 'TL',
                        'ccno'         => '5555444433332222',
                        'expDate'      => '2201',
                        'cvc'          => '123',
                    ],
                ],
            ],
        ];
    }

    public static function nonSecurePaymentPostRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'          => '2020110828BC',
                    'ref_ret_num' => '019676067890000191',
                    'amount'      => 10.02,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'installment' => 2,
                ],
                'expected' => [
                    'mid'              => '6706598320',
                    'tid'              => '67005551',
                    'tranDateRequired' => '1',
                    'capt'             => [
                        'hostLogKey'   => '019676067890000191',
                        'amount'       => 1002,
                        'currencyCode' => 'TL',
                        'installment'  => '02',
                    ],
                ],
            ],
        ];
    }

    public static function statusRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'            => '2020110828BC',
                    'payment_model' => PosInterface::MODEL_3D_SECURE,
                ],
                'expected' => [
                    'mid'       => '6706598320',
                    'tid'       => '67005551',
                    'agreement' => [
                        'orderID' => 'TDSC000000002020110828BC',
                    ],
                ],
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            'with_order_id'    => [
                'order'    => [
                    'id'            => '2020110828BC',
                    'payment_model' => PosInterface::MODEL_3D_SECURE,
                    'amount'        => 50,
                    'currency'      => PosInterface::CURRENCY_TRY,
                ],
                'tx_type'  => PosInterface::TX_TYPE_REFUND,
                'expected' => [
                    'mid'              => '6706598320',
                    'tid'              => '67005551',
                    'tranDateRequired' => '1',
                    'return'           => [
                        'amount'       => 5000,
                        'currencyCode' => 'TL',
                        'orderID'      => 'TDSC000000002020110828BC',
                    ],
                ],
            ],
            'with_ref_ret_num' => [
                'order'    => [
                    'ref_ret_num'   => '019676067890000191',
                    'payment_model' => PosInterface::MODEL_3D_SECURE,
                    'amount'        => 50,
                    'currency'      => PosInterface::CURRENCY_TRY,
                ],
                'tx_type'  => PosInterface::TX_TYPE_REFUND,
                'expected' => [
                    'mid'              => '6706598320',
                    'tid'              => '67005551',
                    'tranDateRequired' => '1',
                    'return'           => [
                        'amount'       => 5000,
                        'currencyCode' => 'TL',
                        'hostLogKey'   => '019676067890000191',
                    ],
                ],
            ],
        ];
    }

    public static function threeDEnrollmentCheckRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'          => 'TST_190620093100_024',
                    'amount'      => 1.75,
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                ],
                'tx_type'  => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => [
                    'mid'            => '6706598320',
                    'tid'            => '67005551',
                    'oosRequestData' => [
                        'posnetid'       => '27426',
                        'ccno'           => '5555444433332222',
                        'expDate'        => '2201',
                        'cvc'            => '123',
                        'amount'         => 175,
                        'currencyCode'   => 'TL',
                        'installment'    => '00',
                        'XID'            => 'TST_190620093100_024',
                        'cardHolderName' => 'ahmet',
                        'tranType'       => 'Sale',
                    ],
                ],
            ],
        ];
    }

    public static function resolveMerchantDataDataProvider(): array
    {
        return [
            [
                'order'         => [
                    'id'          => '2020110828BC',
                    'amount'      => 100.01,
                    'installment' => '0',
                    'currency'    => PosInterface::CURRENCY_TRY,
                ],
                'response_data' => [
                    'BankPacket'     => 'F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                    'MerchantPacket' => 'E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                    'Sign'           => '9998F61E1D0C0FB6EC5203A748124F30',
                ],
                'expected'      => [
                    'mid'                    => '6706598320',
                    'tid'                    => '67005551',
                    'oosResolveMerchantData' => [
                        'bankData'     => 'F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                        'merchantData' => 'E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                        'sign'         => '9998F61E1D0C0FB6EC5203A748124F30',
                        'mac'          => 'oE7zwV87uOc2DFpGPlr4jQRQ0z9LsxGw56c7vaiZkTo=',
                    ],
                ],
            ],
        ];
    }
}

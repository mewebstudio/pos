<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PayForPosRequestDataMapper;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\PayForPosRequestDataMapper
 */
class PayForPosRequestDataMapperTest extends TestCase
{
    private PayForAccount $account;

    private CreditCardInterface $card;

    private PayForPosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayForAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            PosInterface::MODEL_3D_SECURE,
            '12345678'
        );

        $this->crypt      = $this->createMock(CryptInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->requestDataMapper = new PayForPosRequestDataMapper($this->dispatcher, $this->crypt);
        $this->card              = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet');
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
     * @testWith ["PreAuth"]
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
        $this->assertSame('949', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_TRY]));
        $this->assertSame('978', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_EUR]));
    }

    /**
     * @param string|int|null $installment
     * @param string|int      $expected
     *
     * @testWith ["0", "0"]
     *           ["1", "0"]
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
     * @dataProvider createNonSecurePostAuthPaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, string $txType, array $expected): void
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            $order,
            $txType,
            $this->card
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createCancelRequestDataDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider orderHistoryRequestDataProvider
     */
    public function testOrderCreateHistoryRequestData(array $order, array $expectedData): void
    {
        $actualData = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider historyRequestDataProvider
     */
    public function testCreateHistoryRequestData(array $data, array $expectedData): void
    {
        $actualData = $this->requestDataMapper->createHistoryRequestData($this->account, $data);

        \ksort($expectedData);
        \ksort($actualData);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(array $order, array $responseData, array $expected): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, '', $responseData);

        $this->assertSame($expected, $actual);
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
    ): void {
        $card = $isWithCard ? $this->card : null;

        $this->crypt->expects(self::once())
            ->method('create3DHash')
            ->willReturn($expected['inputs']['Hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['inputs']['Rnd']);

        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with($this->callback(static fn ($dispatchedEvent): bool => $dispatchedEvent instanceof Before3DFormHashCalculatedEvent
                && PayForPos::class === $dispatchedEvent->getGatewayClass()
                && $txType === $dispatchedEvent->getTxType()
                && $paymentModel === $dispatchedEvent->getPaymentModel()
                && count($dispatchedEvent->getFormInputs()) > 3));

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayURL,
            $card
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createStatusRequestDataDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected): void
    {
        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $this->assertSame($expected, $actualData);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        \ksort($expectedData);
        \ksort($actual);
        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider createCustomQueryRequestDataDataProvider
     */
    public function testCreateCustomQueryRequestData(array $requestData, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data_point_inquiry' => [
            'request_data' => [
                'SecureType'     => 'Inquiry',
                'TxnType'        => 'ParaPuanInquiry',
                'Pan'            => '4155650100416111',
                'Expiry'         => '0125',
                'Cvv2'           => '123',
            ],
            'expected'     => [
                'Cvv2'           => '123',
                'Expiry'         => '0125',
                'MbrId'          => '5',
                'MerchantId'     => '085300000009704',
                'Pan'            => '4155650100416111',
                'SecureType'     => 'Inquiry',
                'TxnType'        => 'ParaPuanInquiry',
                'UserCode'       => 'QNB_API_KULLANICI_3DPAY',
                'UserPass'       => 'UcBN0',
            ],
        ];

        yield 'with_account_data_point_inquiry' => [
            'request_data' => [
                'Cvv2'           => '123',
                'Expiry'         => '0125',
                'MbrId'          => '5',
                'MerchantId'     => '085300000009704',
                'Pan'            => '4155650100416111',
                'SecureType'     => 'Inquiry',
                'TxnType'        => 'ParaPuanInquiry',
                'UserCode'       => 'QNB_API_KULLANICI_3DPAYxxx',
                'UserPass'       => 'UcBN0xxx',
            ],
            'expected'     => [
                'Cvv2'           => '123',
                'Expiry'         => '0125',
                'MbrId'          => '5',
                'MerchantId'     => '085300000009704',
                'Pan'            => '4155650100416111',
                'SecureType'     => 'Inquiry',
                'TxnType'        => 'ParaPuanInquiry',
                'UserCode'       => 'QNB_API_KULLANICI_3DPAYxxx',
                'UserPass'       => 'UcBN0xxx',
            ],
        ];
    }

    public static function create3DPaymentRequestDataDataProvider(): array
    {
        return [
            [
                'order'         => [
                    'id' => '2020110828BC',

                ],
                'response_data' => [
                    'RequestGuid' => '1000000057437884',
                ],
                'expected'      => [
                    'RequestGuid' => '1000000057437884',
                    'UserCode'    => 'QNB_API_KULLANICI_3DPAY',
                    'UserPass'    => 'UcBN0',
                    'OrderId'     => '2020110828BC',
                    'SecureType'  => '3DModelPayment',
                ],
            ],
        ];
    }

    public static function createCancelRequestDataDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'       => '2020110828BC',
                    'currency' => PosInterface::CURRENCY_TRY,
                ],
                'expected' => [
                    'MerchantId' => '085300000009704',
                    'UserCode'   => 'QNB_API_KULLANICI_3DPAY',
                    'UserPass'   => 'UcBN0',
                    'MbrId'      => '5',
                    'OrgOrderId' => '2020110828BC',
                    'SecureType' => 'NonSecure',
                    'TxnType'    => 'Void',
                    'Currency'   => '949',
                    'Lang'       => 'tr',
                ],
            ],
        ];
    }

    public static function nonSecurePaymentRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'     => '2020110828BC',
                    'amount' => 100.01,
                ],
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => [
                    'MerchantId'       => '085300000009704',
                    'UserCode'         => 'QNB_API_KULLANICI_3DPAY',
                    'UserPass'         => 'UcBN0',
                    'MbrId'            => '5',
                    'MOTO'             => '0',
                    'OrderId'          => '2020110828BC',
                    'SecureType'       => 'NonSecure',
                    'TxnType'          => 'Auth',
                    'PurchAmount'      => '100.01',
                    'Currency'         => '949',
                    'InstallmentCount' => '0',
                    'Lang'             => 'tr',
                    'CardHolderName'   => 'ahmet',
                    'Pan'              => '5555444433332222',
                    'Expiry'           => '0122',
                    'Cvv2'             => '123',
                ],
            ],
        ];
    }

    public static function createNonSecurePostAuthPaymentRequestDataDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'          => '2020110828BC',
                    'amount'      => 100.01,
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'lang'        => PosInterface::LANG_TR,
                ],
                'expected' => [
                    'MerchantId'  => '085300000009704',
                    'UserCode'    => 'QNB_API_KULLANICI_3DPAY',
                    'UserPass'    => 'UcBN0',
                    'MbrId'       => '5',
                    'OrgOrderId'  => '2020110828BC',
                    'SecureType'  => 'NonSecure',
                    'TxnType'     => 'PostAuth',
                    'PurchAmount' => '100.01',
                    'Currency'    => '949',
                    'Lang'        => 'tr',
                ],
            ],
        ];
    }


    public static function createStatusRequestDataDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id' => '2020110828BC',
                ],
                'expected' => [
                    'MerchantId' => '085300000009704',
                    'UserCode'   => 'QNB_API_KULLANICI_3DPAY',
                    'UserPass'   => 'UcBN0',
                    'MbrId'      => '5',
                    'OrgOrderId' => '2020110828BC',
                    'SecureType' => 'Inquiry',
                    'Lang'       => 'tr',
                    'TxnType'    => 'OrderInquiry',
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
                    'MerchantId' => '085300000009704',
                    'UserCode'   => 'QNB_API_KULLANICI_3DPAY',
                    'UserPass'   => 'UcBN0',
                    'MbrId'      => '5',
                    'SecureType' => 'Report',
                    'TxnType'    => 'TxnHistory',
                    'Lang'       => 'tr',
                    'OrderId'    => '2020110828BC',
                ],
            ],
        ];
    }

    public static function historyRequestDataProvider(): array
    {
        return [
            [
                'data'     => [
                    'transaction_date' => new \DateTime('2022-05-18'),
                ],
                'expected' => [
                    'MerchantId' => '085300000009704',
                    'UserCode'   => 'QNB_API_KULLANICI_3DPAY',
                    'UserPass'   => 'UcBN0',
                    'MbrId'      => '5',
                    'SecureType' => 'Report',
                    'TxnType'    => 'TxnHistory',
                    'Lang'       => 'tr',
                    'ReqDate'    => '20220518',
                ],
            ],
        ];
    }

    public static function threeDFormDataProvider(): array
    {
        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/success.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/fail.php',
            'lang'        => PosInterface::LANG_TR,
        ];

        return [
            'without_card' => [
                'order'        => $order,
                'gatewayUrl'   => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                    'method'  => 'POST',
                    'inputs'  => [
                        'MbrId'            => '5',
                        'MerchantID'       => '085300000009704',
                        'UserCode'         => 'QNB_API_KULLANICI_3DPAY',
                        'OrderId'          => '2020110828BC',
                        'Lang'             => 'tr',
                        'SecureType'       => '3DModel',
                        'TxnType'          => 'Auth',
                        'PurchAmount'      => '100.01',
                        'InstallmentCount' => '0',
                        'Currency'         => '949',
                        'OkUrl'            => 'http://localhost/finansbank-payfor/3d/success.php',
                        'FailUrl'          => 'http://localhost/finansbank-payfor/3d/fail.php',
                        'Rnd'              => '1deda47050cd38112cbf91f4',
                        'Hash'             => 'BSj3xu8dYQbdw5YM4JvTS+vmyUI=',
                    ],
                ],
            ],
            'with_card'    => [
                'order'        => $order,
                'gatewayUrl'   => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => true,
                'expected'     => [
                    'gateway' => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                    'method'  => 'POST',
                    'inputs'  => [
                        'MbrId'            => '5',
                        'MerchantID'       => '085300000009704',
                        'UserCode'         => 'QNB_API_KULLANICI_3DPAY',
                        'OrderId'          => '2020110828BC',
                        'Lang'             => 'tr',
                        'SecureType'       => '3DModel',
                        'TxnType'          => 'Auth',
                        'PurchAmount'      => '100.01',
                        'InstallmentCount' => '0',
                        'Currency'         => '949',
                        'OkUrl'            => 'http://localhost/finansbank-payfor/3d/success.php',
                        'FailUrl'          => 'http://localhost/finansbank-payfor/3d/fail.php',
                        'Rnd'              => '1deda47050cd38112cbf91f4',
                        'CardHolderName'   => 'ahmet',
                        'Pan'              => '5555444433332222',
                        'Expiry'           => '0122',
                        'Cvv2'             => '123',
                        'Hash'             => 'BSj3xu8dYQbdw5YM4JvTS+vmyUI=',
                    ],
                ],
            ],
            '3d_host'      => [
                'order'        => $order,
                'gatewayUrl'   => 'https://vpostest.qnbfinansbank.com/Gateway/3DHost.aspx',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://vpostest.qnbfinansbank.com/Gateway/3DHost.aspx',
                    'method'  => 'POST',
                    'inputs'  => [
                        'MbrId'            => '5',
                        'MerchantID'       => '085300000009704',
                        'UserCode'         => 'QNB_API_KULLANICI_3DPAY',
                        'OrderId'          => '2020110828BC',
                        'Lang'             => 'tr',
                        'SecureType'       => '3DHost',
                        'TxnType'          => 'Auth',
                        'PurchAmount'      => '100.01',
                        'InstallmentCount' => '0',
                        'Currency'         => '949',
                        'OkUrl'            => 'http://localhost/finansbank-payfor/3d/success.php',
                        'FailUrl'          => 'http://localhost/finansbank-payfor/3d/fail.php',
                        'Rnd'              => '1deda47050cd38112cbf91f4',
                        'Hash'             => 'BSj3xu8dYQbdw5YM4JvTS+vmyUI=',
                    ],
                ],
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            'full_refund'    => [
                'order'    => [
                    'id'       => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 5,
                ],
                'txType'   => PosInterface::TX_TYPE_REFUND,
                'expected' => [
                    'Currency'    => '949',
                    'Lang'        => 'tr',
                    'MbrId'       => '5',
                    'MerchantId'  => '085300000009704',
                    'OrgOrderId'  => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'PurchAmount' => '5',
                    'SecureType'  => 'NonSecure',
                    'TxnType'     => 'Refund',
                    'UserCode'    => 'QNB_API_KULLANICI_3DPAY',
                    'UserPass'    => 'UcBN0',
                ],
            ],
            'partial_refund' => [
                'order'    => [
                    'id'       => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 5,
                ],
                'txType'   => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'expected' => [
                    'Currency'    => '949',
                    'Lang'        => 'tr',
                    'MbrId'       => '5',
                    'MerchantId'  => '085300000009704',
                    'OrgOrderId'  => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'PurchAmount' => '5',
                    'SecureType'  => 'NonSecure',
                    'TxnType'     => 'Refund',
                    'UserCode'    => 'QNB_API_KULLANICI_3DPAY',
                    'UserPass'    => 'UcBN0',
                ],
            ],
        ];
    }
}

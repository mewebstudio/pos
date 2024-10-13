<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PayForPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestValueFormatter\PayForPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueMapper\PayForPosRequestValueMapper;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
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
    /**
     * @var PayForAccount[]
     */
    private array $accounts;

    private CreditCardInterface $card;

    private PayForPosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;
    private PayForPosRequestValueFormatter $valueFormatter;
    private PayForPosRequestValueMapper $valueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accounts['finansbank'] = AccountFactory::createPayForAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            PosInterface::MODEL_3D_SECURE,
            '12345678',
            PosInterface::LANG_TR,
            PayForAccount::MBR_ID_FINANSBANK
        );

        $this->accounts['ziraat_katilim'] = AccountFactory::createPayForAccount(
            'ziraat-katilim',
            '085300000009704',
            'ZIRAATKATILIM_API_KULLANICI_3DPAY',
            'UcBN0',
            PosInterface::MODEL_3D_SECURE,
            '12345678',
            PosInterface::LANG_TR,
            PayForAccount::MBR_ID_ZIRAAT_KATILIM
        );

        $this->crypt          = $this->createMock(CryptInterface::class);
        $this->dispatcher     = $this->createMock(EventDispatcherInterface::class);
        $this->valueFormatter = new PayForPosRequestValueFormatter();
        $this->valueMapper    = new PayForPosRequestValueMapper();

        $this->requestDataMapper = new PayForPosRequestDataMapper(
            $this->valueMapper,
            $this->valueFormatter,
            $this->dispatcher,
            $this->crypt,
        );

        $this->card = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet');
    }

    /**
     * @dataProvider createNonSecurePostAuthPaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(string $account, array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->accounts[$account], $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(string $account, array $order, string $txType, array $expected): void
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->accounts[$account],
            $order,
            $txType,
            $this->card
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createCancelRequestDataDataProvider
     */
    public function testCreateCancelRequestData(string $account, array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createCancelRequestData($this->accounts[$account], $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider orderHistoryRequestDataProvider
     */
    public function testOrderCreateHistoryRequestData(string $account, array $order, array $expectedData): void
    {
        $actualData = $this->requestDataMapper->createOrderHistoryRequestData($this->accounts[$account], $order);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider historyRequestDataProvider
     */
    public function testCreateHistoryRequestData(string $account, array $data, array $expectedData): void
    {
        $actualData = $this->requestDataMapper->createHistoryRequestData($this->accounts[$account], $data);

        \ksort($expectedData);
        \ksort($actualData);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(string $account, array $order, array $responseData, array $expected): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->accounts[$account], $order, '', $responseData);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(
        string $account,
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
            $this->accounts[$account],
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
    public function testCreateStatusRequestData(string $account, array $order, array $expected): void
    {
        $actualData = $this->requestDataMapper->createStatusRequestData($this->accounts[$account], $order);

        $this->assertSame($expected, $actualData);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testCreateRefundRequestData(string $account, array $order, string $txType, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createRefundRequestData($this->accounts[$account], $order, $txType);

        \ksort($expectedData);
        \ksort($actual);
        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider createCustomQueryRequestDataDataProvider
     */
    public function testCreateCustomQueryRequestData(string $account, array $requestData, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createCustomQueryRequestData($this->accounts[$account], $requestData);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data_point_inquiry' => [
            'account'      => 'finansbank',
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
            'account'      => 'finansbank',
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

        yield 'without_account_data_point_inquiry_ziraat_katilim' => [
            'account'      => 'ziraat_katilim',
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
                'MbrId'          => '12',
                'MerchantId'     => '085300000009704',
                'Pan'            => '4155650100416111',
                'SecureType'     => 'Inquiry',
                'TxnType'        => 'ParaPuanInquiry',
                'UserCode'       => 'ZIRAATKATILIM_API_KULLANICI_3DPAY',
                'UserPass'       => 'UcBN0',
            ],
        ];
    }

    public static function create3DPaymentRequestDataDataProvider(): array
    {
        return [
            [
                'account'       => 'finansbank',
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
                'account'  => 'finansbank',
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
            [
                'account'  => 'ziraat_katilim',
                'order'    => [
                    'id'       => '2020110828BC',
                    'currency' => PosInterface::CURRENCY_TRY,
                ],
                'expected' => [
                    'MerchantId' => '085300000009704',
                    'UserCode'   => 'ZIRAATKATILIM_API_KULLANICI_3DPAY',
                    'UserPass'   => 'UcBN0',
                    'MbrId'      => '12',
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
                'account'   => 'finansbank',
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
            [
                'account'   => 'ziraat_katilim',
                'order'    => [
                    'id'     => '2020110828BC',
                    'amount' => 100.01,
                ],
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => [
                    'MerchantId'       => '085300000009704',
                    'UserCode'         => 'ZIRAATKATILIM_API_KULLANICI_3DPAY',
                    'UserPass'         => 'UcBN0',
                    'MbrId'            => '12',
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
        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'lang'        => PosInterface::LANG_TR,
        ];

        return [
            [
                'account'  => 'finansbank',
                'order'    => $order,
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
            [
                'account'  => 'ziraat_katilim',
                'order'    => $order,
                'expected' => [
                    'MerchantId'  => '085300000009704',
                    'UserCode'    => 'ZIRAATKATILIM_API_KULLANICI_3DPAY',
                    'UserPass'    => 'UcBN0',
                    'MbrId'       => '12',
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
                'account'  => 'finansbank',
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
            [
                'account'  => 'ziraat_katilim',
                'order'    => [
                    'id' => '2020110828BC',
                ],
                'expected' => [
                    'MerchantId' => '085300000009704',
                    'UserCode'   => 'ZIRAATKATILIM_API_KULLANICI_3DPAY',
                    'UserPass'   => 'UcBN0',
                    'MbrId'      => '12',
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
                'account'  => 'finansbank',
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
            [
                'account'  => 'ziraat_katilim',
                'order'    => [
                    'id' => '2020110828BC',
                ],
                'expected' => [
                    'MerchantId' => '085300000009704',
                    'UserCode'   => 'ZIRAATKATILIM_API_KULLANICI_3DPAY',
                    'UserPass'   => 'UcBN0',
                    'MbrId'      => '12',
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
                'account'  => 'finansbank',
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
            [
                'account'  => 'ziraat_katilim',
                'data'     => [
                    'transaction_date' => new \DateTime('2022-05-18'),
                ],
                'expected' => [
                    'MerchantId' => '085300000009704',
                    'UserCode'   => 'ZIRAATKATILIM_API_KULLANICI_3DPAY',
                    'UserPass'   => 'UcBN0',
                    'MbrId'      => '12',
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
                'account'      => 'finansbank',
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
                'account'      => 'finansbank',
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
            'with_card_ziraat_katilim'    => [
                'account'      => 'ziraat_katilim',
                'order'        => $order,
                'gatewayUrl'   => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => true,
                'expected'     => [
                    'gateway' => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                    'method'  => 'POST',
                    'inputs'  => [
                        'MbrId'            => '12',
                        'MerchantID'       => '085300000009704',
                        'UserCode'         => 'ZIRAATKATILIM_API_KULLANICI_3DPAY',
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
                'account'      => 'finansbank',
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
                'account'  => 'finansbank',
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
            'full_refund_ziraat_katilim'    => [
                'account'  => 'ziraat_katilim',
                'order'    => [
                    'id'       => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 5,
                ],
                'txType'   => PosInterface::TX_TYPE_REFUND,
                'expected' => [
                    'Currency'    => '949',
                    'Lang'        => 'tr',
                    'MbrId'       => '12',
                    'MerchantId'  => '085300000009704',
                    'OrgOrderId'  => '7022b92e-3aa1-44fb-86d4-33658c700c80',
                    'PurchAmount' => '5',
                    'SecureType'  => 'NonSecure',
                    'TxnType'     => 'Refund',
                    'UserCode'    => 'ZIRAATKATILIM_API_KULLANICI_3DPAY',
                    'UserPass'    => 'UcBN0',
                ],
            ],
            'partial_refund' => [
                'account'  => 'finansbank',
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

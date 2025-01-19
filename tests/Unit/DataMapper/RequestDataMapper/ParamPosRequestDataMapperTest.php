<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\ParamPosRequestDataMapper;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\ParamPosResponseDataMapperTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\ParamPosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class ParamPosRequestDataMapperTest extends TestCase
{
    private ParamPosAccount $account;

    private static CreditCardInterface $card;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    private ParamPosRequestDataMapper $requestDataMapper;

    public static function setUpBeforeClass(): void
    {
        self::$card = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createParamPosAccount(
            'param-pos',
            10738,
            'Test1',
            'Test2',
            '0c13d406-873b-403b-9c09-a5766840d98c'
        );

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new ParamPosRequestDataMapper($this->dispatcher, $this->crypt);
    }

    /**
     * @testWith ["1"]
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
        $this->assertSame('1000', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_TRY]));
        $this->assertSame('1001', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_USD]));
        $this->assertSame('1002', $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_EUR]));
    }

    /**
     * @param string|int|null $installment
     * @param string|int      $expected
     *
     * @testWith ["0", "1"]
     *           ["1", "1"]
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
     * @testWith [10.0, "10,00"]
     *            [1000.0, "1000,00"]
     *            [1000.5, "1000,50"]
     */
    public function testFormatAmount(float $amount, string $formattedAmount): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('formatAmount');
        $method->setAccessible(true);
        $this->assertSame($formattedAmount, $method->invokeArgs($this->requestDataMapper, [$amount]));
    }

    /**
     * @dataProvider nonSecurePaymentPostRequestDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider paymentRegisterRequestDataProvider
     */
    public function testCreate3DEnrollmentCheckRequestData(array $order, string $paymentModel, string $txType, ?CreditCardInterface $card, string $soapAction, array $expected): void
    {

        $requestDataWithoutHash = $expected;
        $soapBody               = $expected['soap:Body'];
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($soapBody[$soapAction]['Islem_ID']);

        if (PosInterface::MODEL_3D_HOST !== $paymentModel) {
            unset($requestDataWithoutHash['soap:Body'][$soapAction]['Islem_Hash']);
            $this->crypt->expects(self::once())
                ->method('createHash')
                ->with($this->account, $this->callback(function (array $actual) use ($requestDataWithoutHash, $soapAction): bool {
                    $expected = $requestDataWithoutHash['soap:Body'];
                    ksort($actual[$soapAction]);
                    ksort($expected[$soapAction]);
                    $this->assertSame($expected, $actual);

                    return true;
                }))
                ->willReturn($soapBody[$soapAction]['Islem_Hash']);
        }

        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $this->account,
            $order,
            $card,
            $txType,
            $paymentModel
        );

        ksort($actual);
        ksort($expected);
        ksort($actual['soap:Body'][$soapAction]);
        ksort($expected['soap:Body'][$soapAction]);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider paymentRegisterRequestDataProviderException
     */
    public function testCreate3DEnrollmentCheckRequestException(
        array                $order,
        string               $paymentModel,
        string               $txType,
        ?CreditCardInterface $card,
        string               $expectedException
    ): void {
        $this->expectException($expectedException);
        $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $this->account,
            $order,
            $card,
            $txType,
            $paymentModel
        );
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, string $txType, string $soapAction, array $expected): void
    {
        $requestDataWithoutHash = $expected;
        $soapBody               = $expected['soap:Body'];
        unset($requestDataWithoutHash['soap:Body'][$soapAction]['Islem_Hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($soapBody[$soapAction]['Islem_ID']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $this->callback(function (array $actual) use ($requestDataWithoutHash, $soapAction): bool {
                $expected = $requestDataWithoutHash['soap:Body'];
                ksort($actual[$soapAction]);
                ksort($expected[$soapAction]);
                $this->assertSame($expected, $actual);
                return true;
            }))
            ->willReturn($soapBody[$soapAction]['Islem_Hash']);

        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            $order,
            $txType,
            self::$card
        );

        ksort($actual);
        ksort($expected);
        ksort($actual['soap:Body'][$soapAction]);
        ksort($expected['soap:Body'][$soapAction]);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider cancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, string $soapAction, array $expected): void
    {
        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);
        ksort($actual['soap:Body'][$soapAction]);
        ksort($expected['soap:Body'][$soapAction]);

        $this->assertSame($expected, $actual);
    }

    public function testCreateOrderHistoryRequestData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->createOrderHistoryRequestData($this->account, []);
    }


    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(
        array   $order,
        string  $txType,
        string  $paymentModel,
        bool    $withCard,
        ?string $gatewayURL,
        array   $extraData,
        $expected
    ): void {
        $card = $withCard ? self::$card : null;

        $this->crypt->expects(self::never())
            ->method('create3DHash');

        $this->crypt->expects(self::never())
            ->method('generateRandomString');

        $this->dispatcher->expects(self::never())
            ->method('dispatch');

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayURL,
            $card,
            $extraData
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider threeDFormDataProviderFail
     */
    public function testGet3DFormDataFail(
        array  $order,
        string $txType,
        string $paymentModel,
        bool   $withCard,
        ?string $gatewayURL,
        array  $extraData,
        string $expectedException
    ): void {
        $card = $withCard ? self::$card : null;

        $this->crypt->expects(self::never())
            ->method('create3DHash');

        $this->crypt->expects(self::never())
            ->method('generateRandomString');

        $this->dispatcher->expects(self::never())
            ->method('dispatch');

        $this->expectException($expectedException);

        $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayURL,
            $card,
            $extraData
        );
    }

    /**
     * @dataProvider statusRequestDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected): void
    {
        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $this->assertSame($expected, $actualData);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expected): void
    {
        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        ksort($actual);
        ksort($expected);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(array $order, string $txType, array $responseData, array $expected): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData(
            $this->account,
            $order,
            $txType,
            $responseData
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider historyRequestDataProvider
     */
    public function testCreateHistoryRequestData(array $data, array $expected): void
    {
        $actualData = $this->requestDataMapper->createHistoryRequestData($this->account, $data);
        ksort($actualData);
        ksort($expected);
        $this->assertSame($expected, $actualData);
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

    public static function historyRequestDataProvider(): iterable
    {

        yield 'with_date_range' => [
            'order'    => [
                'start_date' => new \DateTimeImmutable('2024-04-13 13:00:00'),
                'end_date'   => new \DateTimeImmutable('2024-04-14 13:00:00'),
            ],
            'expected' => [
                'soap:Body' => [
                    'TP_Islem_Izleme' => [
                        'G'         => [
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                        'GUID'      => '0c13d406-873b-403b-9c09-a5766840d98c',
                        '@xmlns'    => 'https://turkpos.com.tr/',
                        'Tarih_Bas' => '13.04.2024 13:00:00',
                        'Tarih_Bit' => '14.04.2024 13:00:00',
                    ],
                ],
            ],
        ];
        yield 'with_date_range_and_other_params_1' => [
            'order'    => [
                'start_date'       => new \DateTimeImmutable('2024-04-13 13:00:00'),
                'end_date'         => new \DateTimeImmutable('2024-04-14 13:00:00'),
                'order_status'     => 'Başarılı',
                'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            'expected' => [
                'soap:Body' => [
                    'TP_Islem_Izleme' => [
                        'G'           => [
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                        'GUID'        => '0c13d406-873b-403b-9c09-a5766840d98c',
                        '@xmlns'      => 'https://turkpos.com.tr/',
                        'Tarih_Bas'   => '13.04.2024 13:00:00',
                        'Tarih_Bit'   => '14.04.2024 13:00:00',
                        'Islem_Durum' => 'Başarılı',
                        'Islem_Tip'   => 'Satış',
                    ],
                ],
            ],
        ];

        yield 'with_date_range_and_other_params_canceled_orders' => [
            'order'    => [
                'start_date'       => new \DateTimeImmutable('2024-04-13 13:00:00'),
                'end_date'         => new \DateTimeImmutable('2024-04-14 13:00:00'),
                'order_status'     => 'Başarılı',
                'transaction_type' => PosInterface::TX_TYPE_CANCEL,
            ],
            'expected' => [
                'soap:Body' => [
                    'TP_Islem_Izleme' => [
                        'G'           => [
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                        'GUID'        => '0c13d406-873b-403b-9c09-a5766840d98c',
                        '@xmlns'      => 'https://turkpos.com.tr/',
                        'Tarih_Bas'   => '13.04.2024 13:00:00',
                        'Tarih_Bit'   => '14.04.2024 13:00:00',
                        'Islem_Durum' => 'Başarılı',
                        'Islem_Tip'   => 'İptal',
                    ],
                ],
            ],
        ];

        yield 'with_date_range_and_other_params_canceled_refunded' => [
            'order'    => [
                'start_date'       => new \DateTimeImmutable('2024-04-13 13:00:00'),
                'end_date'         => new \DateTimeImmutable('2024-04-14 13:00:00'),
                'order_status'     => 'Başarılı',
                'transaction_type' => PosInterface::TX_TYPE_REFUND,
            ],
            'expected' => [
                'soap:Body' => [
                    'TP_Islem_Izleme' => [
                        'G'           => [
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                        'GUID'        => '0c13d406-873b-403b-9c09-a5766840d98c',
                        '@xmlns'      => 'https://turkpos.com.tr/',
                        'Tarih_Bas'   => '13.04.2024 13:00:00',
                        'Tarih_Bit'   => '14.04.2024 13:00:00',
                        'Islem_Durum' => 'Başarılı',
                        'Islem_Tip'   => 'İade',
                    ],
                ],
            ],
        ];
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data_installment_option_inquiry' => [
            'request_data' => [
                'TP_Ozel_Oran_Liste' => [
                    '@xmlns' => 'https://turkpos.com.tr/',
                ],
            ],
            'expected'     => [
                'soap:Body' => [
                    'TP_Ozel_Oran_Liste' => [
                        '@xmlns' => 'https://turkpos.com.tr/',
                        'G'      => [
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                        'GUID'   => '0c13d406-873b-403b-9c09-a5766840d98c',
                    ],
                ],
            ],
        ];

        yield 'with_account_data_installment_option_inquiry' => [
            'request_data' => [
                'TP_Ozel_Oran_Liste' => [
                    '@xmlns' => 'https://turkpos.com.tr/x',
                    'G'      => [
                        'CLIENT_CODE'     => '10738x',
                        'CLIENT_USERNAME' => 'Test1x',
                        'CLIENT_PASSWORD' => 'Test2x',
                    ],
                    'GUID'   => '0c13d406-873b-403b-9c09-a5766840d98cx',
                ],
            ],
            'expected'     => [
                'soap:Body' => [
                    'TP_Ozel_Oran_Liste' => [
                        '@xmlns' => 'https://turkpos.com.tr/x',
                        'G'      => [
                            'CLIENT_CODE'     => '10738x',
                            'CLIENT_USERNAME' => 'Test1x',
                            'CLIENT_PASSWORD' => 'Test2x',
                        ],
                        'GUID'   => '0c13d406-873b-403b-9c09-a5766840d98cx',
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
                    'id' => 'id-12',
                ],
                'expected' => [
                    'soap:Body' => [
                        'TP_Islem_Sorgulama4' => [
                            'G'          => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'       => '0c13d406-873b-403b-9c09-a5766840d98c',
                            '@xmlns'     => 'https://turkpos.com.tr/',
                            'Siparis_ID' => 'id-12',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function cancelRequestDataProvider(): array
    {
        return [
            'cancel_pay'      => [
                'order'       => [
                    'id'               => 'id-12',
                    'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
                    'amount'           => 10.0,

                ],
                'soap_action' => 'TP_Islem_Iptal_Iade_Kismi2',
                'expected'    => [
                    'soap:Body' => [
                        'TP_Islem_Iptal_Iade_Kismi2' => [
                            'G'          => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'       => '0c13d406-873b-403b-9c09-a5766840d98c',
                            '@xmlns'     => 'https://turkpos.com.tr/',
                            'Durum'      => 'IPTAL',
                            'Siparis_ID' => 'id-12',
                            'Tutar'      => 10.0,
                        ],
                    ],
                ],
            ],
            'cancel_pre_auth' => [
                'order'       => [
                    'id'               => 'id-12',
                    'transaction_type' => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                    'amount'           => 10.0,

                ],
                'soap_action' => 'TP_Islem_Iptal_OnProv',
                'expected'    => [
                    'soap:Body' => [
                        'TP_Islem_Iptal_OnProv' => [
                            'G'          => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'       => '0c13d406-873b-403b-9c09-a5766840d98c',
                            '@xmlns'     => 'https://turkpos.com.tr/',
                            'Siparis_ID' => 'id-12',
                            'Prov_ID'    => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'     => 'id-12',
                    'amount' => 1.02,
                ],
                'tx_type'  => PosInterface::TX_TYPE_REFUND,
                'expected' => [
                    'soap:Body' => [
                        'TP_Islem_Iptal_Iade_Kismi2' => [
                            'G'          => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'       => '0c13d406-873b-403b-9c09-a5766840d98c',
                            '@xmlns'     => 'https://turkpos.com.tr/',
                            'Durum'      => 'IADE',
                            'Siparis_ID' => 'id-12',
                            'Tutar'      => 1.02,
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function paymentRegisterRequestDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'amount'      => 1000.25,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'ip'          => '127.0.0.1',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail',
        ];

        $card = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);

        return [
            '3d_host'                    => [
                'order'        => $order,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'card'         => null,
                'soapAction'   => 'TO_Pre_Encrypting_OOS',
                'expected'     => [
                    'soap:Body'   => [
                        'TO_Pre_Encrypting_OOS' => [
                            '@xmlns'           => 'https://turkodeme.com.tr/',
                            'Borclu_Aciklama'  => 'r|',
                            'Borclu_AdSoyad'   => 'r|',
                            'Borclu_GSM'       => 'r|',
                            'Borclu_Kisi_TC'   => '',
                            'Borclu_Odeme_Tip' => 'r|Diğer',
                            'Borclu_Tutar'     => 'r|1000,25',
                            'Islem_ID'         => 'rand',
                            'Return_URL'       => 'r|https://domain.com/success',
                            'Taksit'           => '1',
                            'Terminal_ID'      => '10738',
                        ],
                    ],
                    'soap:Header' => [
                        'ServiceSecuritySoapHeader' => [
                            '@xmlns'          => 'https://turkodeme.com.tr/',
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                    ],
                ],
            ],
            '3d_host_foreign_currency'   => [
                'order'        => [
                    'id'          => 'order222',
                    'amount'      => 1000.25,
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_EUR,
                    'ip'          => '127.0.0.1',
                    'success_url' => 'https://domain.com/success',
                    'fail_url'    => 'https://domain.com/fail',
                ],
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'card'         => null,
                'soapAction'   => 'TO_Pre_Encrypting_OOS',
                'expected'     => [
                    'soap:Body'   => [
                        'TO_Pre_Encrypting_OOS' => [
                            '@xmlns'           => 'https://turkodeme.com.tr/',
                            'Borclu_Aciklama'  => 'r|',
                            'Borclu_AdSoyad'   => 'r|',
                            'Borclu_GSM'       => 'r|',
                            'Borclu_Kisi_TC'   => '',
                            'Borclu_Odeme_Tip' => 'r|Diğer',
                            'Borclu_Tutar'     => 'r|1000,25',
                            'Doviz_Kodu'       => '1002',
                            'Islem_ID'         => 'rand',
                            'Return_URL'       => 'r|https://domain.com/success',
                            'Taksit'           => '1',
                            'Terminal_ID'      => '10738',
                        ],
                    ],
                    'soap:Header' => [
                        'ServiceSecuritySoapHeader' => [
                            '@xmlns'          => 'https://turkodeme.com.tr/',
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                    ],
                ],
            ],
            [
                'order'        => $order,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'card'         => $card,
                'soapAction'   => 'TP_WMD_UCD',
                'expected'     => [
                    'soap:Body' => [
                        'TP_WMD_UCD' => [
                            '@xmlns'             => 'https://turkpos.com.tr/',
                            'Islem_ID'           => 'rand',
                            'Islem_Hash'         => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
                            'G'                  => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'               => '0c13d406-873b-403b-9c09-a5766840d98c',
                            'Islem_Guvenlik_Tip' => '3D',
                            'IPAdr'              => '127.0.0.1',
                            'Siparis_ID'         => 'order222',
                            'Islem_Tutar'        => '1000,25',
                            'Toplam_Tutar'       => '1000,25',
                            'Basarili_URL'       => 'https://domain.com/success',
                            'Hata_URL'           => 'https://domain.com/fail',
                            'Taksit'             => '1',
                            'KK_Sahibi'          => 'ahmet',
                            'KK_No'              => '5555444433332222',
                            'KK_SK_Ay'           => '01',
                            'KK_SK_Yil'          => '2022',
                            'KK_CVC'             => '123',
                            'KK_Sahibi_GSM'      => '',
                        ],
                    ],
                ],
            ],
            '3d_secure_foreign_currency' => [
                'order'        => [
                    'id'          => 'order222',
                    'amount'      => 1000.25,
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_USD,
                    'ip'          => '127.0.0.1',
                    'success_url' => 'https://domain.com/success',
                    'fail_url'    => 'https://domain.com/fail',
                ],
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'card'         => $card,
                'soapAction'   => 'TP_Islem_Odeme_WD',
                'expected'     => [
                    'soap:Body' => [
                        'TP_Islem_Odeme_WD' => [
                            'G'                  => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'               => '0c13d406-873b-403b-9c09-a5766840d98c',
                            '@xmlns'             => 'https://turkpos.com.tr/',
                            'Islem_Guvenlik_Tip' => '3D',
                            'Islem_ID'           => '3344914AAB82284E39742F4C',
                            'IPAdr'              => '127.0.0.1',
                            'Siparis_ID'         => 'order222',
                            'Islem_Tutar'        => '1000,25',
                            'Toplam_Tutar'       => '1000,25',
                            'Basarili_URL'       => 'https://domain.com/success',
                            'Hata_URL'           => 'https://domain.com/fail',
                            'Taksit'             => '1',
                            'KK_Sahibi'          => 'ahmet',
                            'KK_No'              => '5555444433332222',
                            'KK_SK_Ay'           => '01',
                            'KK_SK_Yil'          => '2022',
                            'KK_CVC'             => '123',
                            'KK_Sahibi_GSM'      => '',
                            'Doviz_Kodu'         => '1001',
                            'Islem_Hash'         => 'LFZ+Sl0mW+ybGvLr1u0ehZoxhxM=',
                        ],
                    ],
                ],
            ],
            '3d_secure_pre_payment'      => [
                'order'        => [
                    'id'          => 'order222',
                    'amount'      => 1000.25,
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'ip'          => '127.0.0.1',
                    'success_url' => 'https://domain.com/success',
                    'fail_url'    => 'https://domain.com/fail',
                ],
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'card'         => $card,
                'soapAction'   => 'TP_Islem_Odeme_OnProv_WMD',
                'expected'     => [
                    'soap:Body' => [
                        'TP_Islem_Odeme_OnProv_WMD' => [
                            'G'                  => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'               => '0c13d406-873b-403b-9c09-a5766840d98c',
                            '@xmlns'             => 'https://turkpos.com.tr/',
                            'Islem_Guvenlik_Tip' => '3D',
                            'Islem_ID'           => '3344914AAB82284E39742F4C',
                            'IPAdr'              => '127.0.0.1',
                            'Siparis_ID'         => 'order222',
                            'Islem_Tutar'        => '1000,25',
                            'Toplam_Tutar'       => '1000,25',
                            'Basarili_URL'       => 'https://domain.com/success',
                            'Hata_URL'           => 'https://domain.com/fail',
                            'Taksit'             => '1',
                            'KK_Sahibi'          => 'ahmet',
                            'KK_No'              => '5555444433332222',
                            'KK_SK_Ay'           => '01',
                            'KK_SK_Yil'          => '2022',
                            'KK_CVC'             => '123',
                            'KK_Sahibi_GSM'      => '',
                            'Islem_Hash'         => 'LFZ+Sl0mW+ybGvLr1u0ehZoxhxM=',
                        ],
                    ],
                ],
            ],
            '3d_pay'                     => [
                'order'        => [
                    'id'          => 'order222',
                    'amount'      => 1000.25,
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'ip'          => '127.0.0.1',
                    'success_url' => 'https://domain.com/success',
                    'fail_url'    => 'https://domain.com/fail',
                ],
                'paymentModel' => PosInterface::MODEL_3D_PAY,
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'card'         => $card,
                'soapAction'   => 'Pos_Odeme',
                'expected'     => [
                    'soap:Body' => [
                        'Pos_Odeme' => [
                            'G'                  => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'               => '0c13d406-873b-403b-9c09-a5766840d98c',
                            '@xmlns'             => 'https://turkpos.com.tr/',
                            'Islem_Guvenlik_Tip' => '3D',
                            'Islem_ID'           => '3344914AAB82284E39742F4C',
                            'IPAdr'              => '127.0.0.1',
                            'Siparis_ID'         => 'order222',
                            'Islem_Tutar'        => '1000,25',
                            'Toplam_Tutar'       => '1000,25',
                            'Basarili_URL'       => 'https://domain.com/success',
                            'Hata_URL'           => 'https://domain.com/fail',
                            'Taksit'             => '1',
                            'KK_Sahibi'          => 'ahmet',
                            'KK_No'              => '5555444433332222',
                            'KK_SK_Ay'           => '01',
                            'KK_SK_Yil'          => '2022',
                            'KK_CVC'             => '123',
                            'KK_Sahibi_GSM'      => '',
                            'Islem_Hash'         => 'LFZ+Sl0mW+ybGvLr1u0ehZoxhxM=',
                        ],
                    ],
                ],
            ],
            '3d_pay_installment'         => [
                'order'        => [
                    'id'          => 'order222',
                    'amount'      => 1000.25,
                    'installment' => 2,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'ip'          => '127.0.0.1',
                    'success_url' => 'https://domain.com/success',
                    'fail_url'    => 'https://domain.com/fail',
                ],
                'paymentModel' => PosInterface::MODEL_3D_PAY,
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'card'         => $card,
                'soapAction'   => 'Pos_Odeme',
                'expected'     => [
                    'soap:Body' => [
                        'Pos_Odeme' => [
                            'G'                  => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'               => '0c13d406-873b-403b-9c09-a5766840d98c',
                            '@xmlns'             => 'https://turkpos.com.tr/',
                            'Islem_Guvenlik_Tip' => '3D',
                            'Islem_ID'           => '3344914AAB82284E39742F4C',
                            'IPAdr'              => '127.0.0.1',
                            'Siparis_ID'         => 'order222',
                            'Islem_Tutar'        => '1000,25',
                            'Toplam_Tutar'       => '1000,25',
                            'Basarili_URL'       => 'https://domain.com/success',
                            'Hata_URL'           => 'https://domain.com/fail',
                            'Taksit'             => '2',
                            'KK_Sahibi'          => 'ahmet',
                            'KK_No'              => '5555444433332222',
                            'KK_SK_Ay'           => '01',
                            'KK_SK_Yil'          => '2022',
                            'KK_CVC'             => '123',
                            'KK_Sahibi_GSM'      => '',
                            'Islem_Hash'         => 'LFZ+Sl0mW+ybGvLr1u0ehZoxhxM=',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function paymentRegisterRequestDataProviderException(): array
    {
        $order = [
            'id'          => 'order222',
            'amount'      => 1000.25,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'ip'          => '127.0.0.1',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail',
        ];

        return [

            '3d_secure_without_card' => [
                'order'                    => $order,
                'paymentModel'             => PosInterface::MODEL_3D_SECURE,
                'txType'                   => PosInterface::TX_TYPE_PAY_AUTH,
                'card'                     => null,
                'expected_exception_class' => \InvalidArgumentException::class,
            ],
        ];
    }

    public static function nonSecurePaymentRequestDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'amount'      => 100.25,
            'installment' => 0,
            'ip'          => '127.0.0.1',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
        ];

        return [
            [
                'order'       => $order,
                'txType'      => PosInterface::TX_TYPE_PAY_AUTH,
                'soap_action' => 'TP_WMD_UCD',
                'expected'    => [
                    'soap:Body' => [
                        'TP_WMD_UCD' => [
                            '@xmlns'             => 'https://turkpos.com.tr/',
                            'Islem_ID'           => 'rand',
                            'Islem_Hash'         => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
                            'G'                  => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'               => '0c13d406-873b-403b-9c09-a5766840d98c',
                            'Islem_Guvenlik_Tip' => 'NS',
                            'IPAdr'              => '127.0.0.1',
                            'Siparis_ID'         => 'order222',
                            'Islem_Tutar'        => '100,25',
                            'Toplam_Tutar'       => '100,25',
                            'Taksit'             => '1',
                            'KK_Sahibi'          => 'ahmet',
                            'KK_No'              => '5555444433332222',
                            'KK_SK_Ay'           => '01',
                            'KK_SK_Yil'          => '2022',
                            'KK_CVC'             => '123',
                            'KK_Sahibi_GSM'      => '',
                        ],
                    ],
                ],
            ],
            'non_secure_pre_auth'         => [
                'order'       => $order,
                'txType'      => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'soap_action' => 'TP_Islem_Odeme_OnProv_WMD',
                'expected'    => [
                    'soap:Body' => [
                        'TP_Islem_Odeme_OnProv_WMD' => [
                            '@xmlns'             => 'https://turkpos.com.tr/',
                            'Islem_ID'           => 'rand',
                            'Islem_Hash'         => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
                            'G'                  => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'               => '0c13d406-873b-403b-9c09-a5766840d98c',
                            'Islem_Guvenlik_Tip' => 'NS',
                            'IPAdr'              => '127.0.0.1',
                            'Siparis_ID'         => 'order222',
                            'Islem_Tutar'        => '100,25',
                            'Toplam_Tutar'       => '100,25',
                            'Taksit'             => '1',
                            'KK_Sahibi'          => 'ahmet',
                            'KK_No'              => '5555444433332222',
                            'KK_SK_Ay'           => '01',
                            'KK_SK_Yil'          => '2022',
                            'KK_CVC'             => '123',
                            'KK_Sahibi_GSM'      => '',
                        ],
                    ],
                ],
            ],
            'non_secure_foreign_currency' => [
                'order'       => [
                    'id'          => 'order222',
                    'amount'      => 100.25,
                    'installment' => 0,
                    'ip'          => '127.0.0.1',
                    'currency'    => PosInterface::CURRENCY_EUR,
                    'success_url' => 'https://domain.com/success',
                    'fail_url'    => 'https://domain.com/fail',
                ],
                'txType'      => PosInterface::TX_TYPE_PAY_AUTH,
                'soap_action' => 'TP_Islem_Odeme_WD',
                'expected'    => [
                    'soap:Body' => [
                        'TP_Islem_Odeme_WD' => [
                            '@xmlns'             => 'https://turkpos.com.tr/',
                            'Islem_ID'           => 'rand',
                            'Islem_Hash'         => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
                            'G'                  => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'               => '0c13d406-873b-403b-9c09-a5766840d98c',
                            'Islem_Guvenlik_Tip' => 'NS',
                            'IPAdr'              => '127.0.0.1',
                            'Siparis_ID'         => 'order222',
                            'Islem_Tutar'        => '100,25',
                            'Toplam_Tutar'       => '100,25',
                            'Taksit'             => '1',
                            'KK_Sahibi'          => 'ahmet',
                            'KK_No'              => '5555444433332222',
                            'KK_SK_Ay'           => '01',
                            'KK_SK_Yil'          => '2022',
                            'KK_CVC'             => '123',
                            'KK_Sahibi_GSM'      => '',
                            'Doviz_Kodu'         => '1002',
                            'Basarili_URL'       => 'https://domain.com/success',
                            'Hata_URL'           => 'https://domain.com/fail',
                        ],
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
                    'id'     => '2020110828BC',
                    'amount' => 1.10,
                ],
                'expected' => [
                    'soap:Body' => [
                        'TP_Islem_Odeme_OnProv_Kapa' => [
                            'G'          => [
                                'CLIENT_CODE'     => '10738',
                                'CLIENT_USERNAME' => 'Test1',
                                'CLIENT_PASSWORD' => 'Test2',
                            ],
                            'GUID'       => '0c13d406-873b-403b-9c09-a5766840d98c',
                            '@xmlns'     => 'https://turkpos.com.tr/',
                            'Prov_ID'    => '',
                            'Prov_Tutar' => '1,10',
                            'Siparis_ID' => '2020110828BC',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function threeDFormDataProvider(): array
    {
        return [
            '3d_host_form_data'                    => [
                'order'         => [],
                'tx_type'       => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model' => PosInterface::MODEL_3D_HOST,
                'is_with_card'  => false,
                'gateway'       => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'extra_data'    => [
                    'TO_Pre_Encrypting_OOSResponse' => [
                        'TO_Pre_Encrypting_OOSResult' => 'JHnDLmT5yierHIqsHNRU2SR7HLxOpi8o7Eb/oVSiIf35v+Z1uzteqid4wop8SAuykWNFElYyAxGWcIGvTxmhSljuLTcJ3xDMkS3O0jUboNpl5ad6roy/92lDftpV535KmpbxMxStRa+qGT7Tk4BdEIf+Jobr2o1Yl1+ZakWZ+parsTgnodyWl432Hsv2FUNLhuU7H6folMwleaZFPYdFZ+bO1T95opw5pnDWcFkrIuPfAmVRg4cg+al22FQSN/58AXxWBb8jEPrqn+/ojZ+WqncGvw+NB/Mtv9iCDuF+SNQqRig2dRILzWYwcvNxzj/OxcYuNuvO8wYI/iF1kNBBNtaExIunWZyj1tntGeb7UUaDmHD4LmSMUMpgZGugRfUpxm8WL/EE+PnUkLXE7SOG3g==',
                    ],
                ],
                'expected'      => [
                    'gateway' => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                    'method'  => 'GET',
                    'inputs'  => [
                        's' => 'JHnDLmT5yierHIqsHNRU2SR7HLxOpi8o7Eb/oVSiIf35v+Z1uzteqid4wop8SAuykWNFElYyAxGWcIGvTxmhSljuLTcJ3xDMkS3O0jUboNpl5ad6roy/92lDftpV535KmpbxMxStRa+qGT7Tk4BdEIf+Jobr2o1Yl1+ZakWZ+parsTgnodyWl432Hsv2FUNLhuU7H6folMwleaZFPYdFZ+bO1T95opw5pnDWcFkrIuPfAmVRg4cg+al22FQSN/58AXxWBb8jEPrqn+/ojZ+WqncGvw+NB/Mtv9iCDuF+SNQqRig2dRILzWYwcvNxzj/OxcYuNuvO8wYI/iF1kNBBNtaExIunWZyj1tntGeb7UUaDmHD4LmSMUMpgZGugRfUpxm8WL/EE+PnUkLXE7SOG3g==',
                    ],
                ],
            ],
            '3d_secure_form_data'                  => [
                'order'         => [
                    'currency' => PosInterface::CURRENCY_TRY,
                ],
                'tx_type'       => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model' => PosInterface::MODEL_3D_SECURE,
                'is_with_card'  => false,
                'gateway'       => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'extra_data'    => [
                    'TP_WMD_UCDResponse' => [
                        'TP_WMD_UCDResult' => [
                            'Islem_ID'        => '6021846789',
                            'Islem_GUID'      => 'f0f2d1b4-a0f2-4960-8c12-676538c29d05',
                            'UCD_HTML'        => 'HTML_FORM_DATA',
                            'UCD_MD'          => 'tgG78pcMgwAIvxCKJP3F8Xm1hGNFeH7uyE4ceX3VFxBuC4NkS5nfuh4KbXUydAyu15e0yOhEQFa7tPHiJhOyHnkg0ybVZiLz+Xtm77TAr4LEDstob5gTM0L/v+zsVzU16GWKqQhhDYQo3OnksybQqrklw3YHZAr2a6yBn7/X/U+GMk/W3V1k3S2TweJsYOV2S6zHk+XNs1uvt10vVssa6wFuWB7iC+qDhm4Pj2dRZCnKqSoU51H3dHYJhA6iy/grRvd0aJuLasFnoekvAxyVeVUoqV6xGV0/+PwzyqRq5e3MBwNogwFGS7BbgkLbmARNnlK+rTuzZA0pvkGG+cOLinxwAktupiVeaTBz+Q3X4gCzB5FXM+PHVCKiqv0b9Aigqn30KlkPQ+/REZsgBYkgy6dv4sfexE/75aoMcJfzXkCGOmqEvbIQ2NvCvV18uDXtX9lFmkLY9Mlfu073tYxMac9x6TPLrSevl1KRVkNyPFjrms0xtx9XqxB693Jjwh8a38FjqQ4a8UzBJJBfjG6tnw==',
                            'Sonuc'           => '1',
                            'Sonuc_Str'       => 'İşlem Başarılı',
                            'Banka_Sonuc_Kod' => '0',
                            'Siparis_ID'      => '202501179C50',
                        ],
                    ],
                ],
                'expected'      => 'HTML_FORM_DATA',
            ],
            '3d_secure_form_data_foreign_currency' => [
                'order'         => [
                    'currency' => PosInterface::CURRENCY_USD,
                ],
                'tx_type'       => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model' => PosInterface::MODEL_3D_SECURE,
                'is_with_card'  => false,
                'gateway'       => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'extra_data'    => [
                    'TP_Islem_Odeme_WDResponse' => [
                        'TP_Islem_Odeme_WDResult' => [
                            'Islem_ID'        => '6021846804',
                            'UCD_URL'         => 'https://test-pos.param.com.tr/3D_Secure/AkilliKart_3DPay_EST_Doviz.aspx?rURL=TURKPOS_3D_TRAN&SID=de67c915-0f0c-4e2a-ac36-a5e8a61c2167',
                            'Sonuc'           => '1',
                            'Sonuc_Str'       => 'İşlem Başarılı',
                            'Banka_Sonuc_Kod' => '0',
                            'Komisyon_Oran'   => '1.75',
                        ],
                    ],
                ],
                'expected'      => [
                    'gateway' => 'https://test-pos.param.com.tr/3D_Secure/AkilliKart_3DPay_EST_Doviz.aspx',
                    'method'  => 'GET',
                    'inputs'  => [
                        'rURL' => 'TURKPOS_3D_TRAN',
                        'SID'  => 'de67c915-0f0c-4e2a-ac36-a5e8a61c2167',
                    ],
                ],
            ],
            '3d_secure_pre_auth'                   => [
                'order'         => [
                    'currency' => PosInterface::CURRENCY_TRY,
                ],
                'tx_type'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'payment_model' => PosInterface::MODEL_3D_SECURE,
                'is_with_card'  => false,
                'gateway'       => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'extra_data'    => [
                    'TP_Islem_Odeme_OnProv_WMDResponse' => [
                        'TP_Islem_Odeme_OnProv_WMDResult' => [
                            'Islem_ID'        => '6021846944',
                            'Islem_GUID'      => 'eb5b3e9b-06fa-4e6a-8cd9-d14076395689',
                            'UCD_HTML'        => 'HTML_FORM',
                            'UCD_MD'          => 'rjMadIilGSFE+7clr3N/lKE/c2HaUFWybdHL+w0ocSYigaHBHyMyozyJMQ/SqwXiOq6Mm1SDtmhg0YZxI2JuMbDOtWT2qPKz7uGOP/CGdpKr5/eNd/DicvsZkH/I/h84asn0d/atq0wtQxtI6NkVVAsndWdtGF3k5cyGwTxq1XAwyulHjQmZDInUu0e+mXPeyoP9AD56I6cwDKpk/Pzmjzsm7aSNprx5eBaV7eUupq017obIpCm4WieUZA7ykHkanlqnc+kqcbXhknnqj1O9aW8smmiU7b1aQ7ZgsuhXFL+LIwW1nCeiw/jjwOuIo+KgmF0hmAt+7EaOBsCnfokEliKP1KAmURdNez7ON9co7VXlS7Z2jEfCj5A8Y1V+wd2uEEVghHPzWA9dFYFdVcq8JnS1n5jAW6JLbqDmsbYsWg4WeDI5F0tLfnJDS3gxE9S4xgwbnfRRhwBqz2ybPZ8Rpw==',
                            'Sonuc'           => '1',
                            'Sonuc_Str'       => 'İşlem Başarılı',
                            'Banka_Sonuc_Kod' => '0',
                            'Siparis_ID'      => '202501199262',
                        ],
                    ],
                ],
                'expected'      => 'HTML_FORM',
            ],
            '3d_pay'                               => [
                'order'         => [
                    'currency' => PosInterface::CURRENCY_TRY,
                ],
                'tx_type'       => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model' => PosInterface::MODEL_3D_PAY,
                'is_with_card'  => false,
                'gateway'       => null,
                'extra_data'    => [
                    'Pos_OdemeResponse' => [
                        'Pos_OdemeResult' => [
                            'Islem_ID'        => '6021847071',
                            'UCD_URL'         => 'https://test-pos.param.com.tr/3D_Secure/AkilliKart_3DPay_PFO.aspx?rURL=TURKPOS_3D_TRAN&SID=f2771b35-f5fd-434a-a1be-ba4eea554146',
                            'Sonuc'           => '1',
                            'Sonuc_Str'       => 'İşlem Başarılı',
                            'Banka_Sonuc_Kod' => '-1',
                            'Komisyon_Oran'   => '1.01',
                        ],
                    ],
                ],
                'expected'      => [
                    'gateway' => 'https://test-pos.param.com.tr/3D_Secure/AkilliKart_3DPay_PFO.aspx',
                    'method'  => 'GET',
                    'inputs'  => [
                        'rURL' => 'TURKPOS_3D_TRAN',
                        'SID'  => 'f2771b35-f5fd-434a-a1be-ba4eea554146',
                    ],
                ],
            ],
            '3d_pay_with_port_in_url'                               => [
                'order'         => [
                    'currency' => PosInterface::CURRENCY_TRY,
                ],
                'tx_type'       => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model' => PosInterface::MODEL_3D_PAY,
                'is_with_card'  => false,
                'gateway'       => null,
                'extra_data'    => [
                    'Pos_OdemeResponse' => [
                        'Pos_OdemeResult' => [
                            'Islem_ID'        => '6021847071',
                            'UCD_URL'         => 'https://test-pos.param.com.tr:443/3D_Secure/AkilliKart_3DPay_PFO.aspx?rURL=TURKPOS_3D_TRAN&SID=f2771b35-f5fd-434a-a1be-ba4eea554146',
                            'Sonuc'           => '1',
                            'Sonuc_Str'       => 'İşlem Başarılı',
                            'Banka_Sonuc_Kod' => '-1',
                            'Komisyon_Oran'   => '1.01',
                        ],
                    ],
                ],
                'expected'      => [
                    'gateway' => 'https://test-pos.param.com.tr:443/3D_Secure/AkilliKart_3DPay_PFO.aspx',
                    'method'  => 'GET',
                    'inputs'  => [
                        'rURL' => 'TURKPOS_3D_TRAN',
                        'SID'  => 'f2771b35-f5fd-434a-a1be-ba4eea554146',
                    ],
                ],
            ],
        ];
    }

    public static function threeDFormDataProviderFail(): array
    {
        return [
            '3d_host_form_data'                   => [
                'order'              => [],
                'tx_type'            => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model'      => PosInterface::MODEL_3D_HOST,
                'is_with_card'       => false,
                'gateway'            => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'extra_data'         => [
                    'TO_Pre_Encrypting_OOSResponse' => [
                        'TO_Pre_Encrypting_OOSResult' => 'SOAP Güvenlik Hatası.192.168.190.2',
                    ],
                ],
                'expected_exception' => \RuntimeException::class,
            ],
            '3d_host_without_gateway_url'                   => [
                'order'              => [],
                'tx_type'            => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model'      => PosInterface::MODEL_3D_HOST,
                'is_with_card'       => false,
                'gateway'            => null,
                'extra_data'         => [
                    'TO_Pre_Encrypting_OOSResponse' => [
                        'TO_Pre_Encrypting_OOSResult' => 'SOAP Güvenlik Hatası.192.168.190.2',
                    ],
                ],
                'expected_exception' => \InvalidArgumentException::class,
            ],
            '3d_pay_invalid_url'                   => [
                'order'              => [],
                'tx_type'            => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model'      => PosInterface::MODEL_3D_PAY,
                'is_with_card'       => false,
                'gateway'            => null,
                'extra_data' => [
                    'Pos_OdemeResponse' => [
                        'Pos_OdemeResult' => [
                            'UCD_URL' => 'SOAP Güvenlik Hatası.192.168.190.2',
                        ],
                    ],
                ],
                'expected_exception' => \InvalidArgumentException::class,
            ],
            '3d_pay_no_query_params_in_url'                   => [
                'order'              => [],
                'tx_type'            => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model'      => PosInterface::MODEL_3D_PAY,
                'is_with_card'       => false,
                'gateway'            => null,
                'extra_data' => [
                    'Pos_OdemeResponse' => [
                        'Pos_OdemeResult' => [
                            'UCD_URL' => 'https://test-pos.param.com.tr/3D_Secure/AkilliKart_3DPay_PFO.aspx',
                        ],
                    ],
                ],
                'expected_exception' => \InvalidArgumentException::class,
            ],
            '3d_secure_form_data_with_empty_html' => [
                'order'              => [
                    'currency' => PosInterface::CURRENCY_TRY,
                ],
                'tx_type'            => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model'      => PosInterface::MODEL_3D_SECURE,
                'is_with_card'       => false,
                'gateway'            => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'extra_data'         => [
                    'TP_WMD_UCDResponse' => [
                        'TP_WMD_UCDResult' => [
                            'Islem_ID'        => '6021846789',
                            'Islem_GUID'      => 'f0f2d1b4-a0f2-4960-8c12-676538c29d05',
                            'UCD_HTML'        => '',
                            'UCD_MD'          => '',
                            'Sonuc'           => '1',
                            'Sonuc_Str'       => 'İşlem Başarılı',
                            'Banka_Sonuc_Kod' => '0',
                            'Siparis_ID'      => '202501179C50',
                        ],
                    ],
                ],
                'expected_exception' => \RuntimeException::class,
            ],
        ];
    }

    public static function create3DPaymentRequestDataDataProvider(): \Generator
    {
        yield [
            'order'        => [
                'id'       => '2020110828BC',
                'amount'   => 100.25,
                'currency' => PosInterface::CURRENCY_TRY,
                'ip'       => '156.155.154.153',
            ],
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData'],
            'expected'     => [
                'soap:Body' => [
                    'TP_WMD_Pay' => [
                        'G'          => [
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                        'GUID'       => '0c13d406-873b-403b-9c09-a5766840d98c',
                        '@xmlns'     => 'https://turkpos.com.tr/',
                        'UCD_MD'     => '444676:13FDE30917BF65D853787DB838390849D73151A10FC8C1192AC72660F2464521:3473:##190100000',
                        'Islem_GUID' => '8b6868fe-a553-4164-a7cc-528461f21759',
                        'Siparis_ID' => '202412292160',
                    ],
                ],
            ],
        ];
    }
}

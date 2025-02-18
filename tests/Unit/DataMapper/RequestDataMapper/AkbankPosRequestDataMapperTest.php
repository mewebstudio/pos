<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\AkbankPosRequestDataMapper;
use Mews\Pos\Entity\Account\AkbankPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\AkbankPosResponseDataMapperTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AkbankPosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class AkbankPosRequestDataMapperTest extends TestCase
{
    private AkbankPosAccount $account;

    private AkbankPosAccount $subMerchantAccount;

    private CreditCardInterface $card;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    private AkbankPosRequestDataMapper $requestDataMapper;

    private array $order;

    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->card               = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
        $this->account            = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            '2023090417500272654BD9A49CF07574',
            '2023090417500284633D137A249DBBEB',
            '3230323330393034313735303032363031353172675f357637355f3273387373745f7233725f73323333383737335f323272383774767276327672323531355f',
            PosInterface::LANG_TR,
        );
        $this->subMerchantAccount = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            '2023090417500272654BD9A49CF07574',
            '2023090417500284633D137A249DBBEB',
            '3230323330393034313735303032363031353172675f357637355f3273387373745f7233725f73323333383737335f323272383774767276327672323531355f',
            PosInterface::LANG_TR,
            'sub-merchant-id'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'amount'      => 1.10,
            'ip'          => '127.0.0.1',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http:://localhost/success',
            'fail_url'    => 'http:://localhost/fail',
        ];

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->crypt      = $this->createMock(CryptInterface::class);

        $this->requestDataMapper = new AkbankPosRequestDataMapper($this->dispatcher, $this->crypt);
    }

    /**
     * @testWith ["pay", "3d", "3000"]
     * ["pre", "3d", "3004"]
     * ["pre", "regular", "1004"]
     */
    public function testMapTxType(string $txType, string $paymentModel, string $expected): void
    {
        $actual = $this->requestDataMapper->mapTxType($txType, $paymentModel);
        $this->assertSame($expected, $actual);
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
        $this->assertSame(949, $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_TRY]));
        $this->assertSame(978, $method->invokeArgs($this->requestDataMapper, [PosInterface::CURRENCY_EUR]));
    }

    /**
     * @param string|int|null $installment
     * @param string|int      $expected
     *
     * @testWith ["0", 1]
     *           ["1", 1]
     *           ["2", 2]
     *           [2, 2]
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
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(array $order, string $txType, string $paymentModel, bool $withCard, string $gatewayURL, array $expected): void
    {
        $card = $withCard ? $this->card : null;

        $this->crypt->expects(self::once())
            ->method('create3DHash')
            ->willReturn($expected['inputs']['hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['inputs']['randomNumber']);

        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with($this->callback(static fn ($dispatchedEvent): bool => $dispatchedEvent instanceof Before3DFormHashCalculatedEvent
                && AkbankPos::class === $dispatchedEvent->getGatewayClass()
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

        $this->assertSame(23, \strlen($actual['inputs']['requestDateTime']));
        unset($actual['inputs']['requestDateTime']);

        $this->assertSame($expected, $actual);
    }

    public function testGet3DFormDataSubMerchant(): void
    {
        $card     = $this->card;
        $expected = [
            'gateway' => 'https://bank.com/pay',
            'method'  => 'POST',
            'inputs'  => [
                'paymentModel'   => '3D',
                'txnCode'        => '3000',
                'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                'orderId'        => '2020110828BC',
                'lang'           => 'TR',
                'amount'         => '1.10',
                'currencyCode'   => '949',
                'installCount'   => '1',
                'okUrl'          => 'http:://localhost/success',
                'failUrl'        => 'http:://localhost/fail',
                'randomNumber'   => 'random-123',
                'subMerchantId'  => 'sub-merchant-id',
                'creditCard'     => '5555444433332222',
                'expiredDate'    => '0122',
                'cvv'            => '123',
                'hash'           => 'hash-abc',
            ],
        ];


        $this->crypt->expects(self::once())
            ->method('create3DHash')
            ->willReturn('hash-abc');

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('random-123');

        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;

        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with($this->callback(static fn ($dispatchedEvent): bool => $dispatchedEvent instanceof Before3DFormHashCalculatedEvent
                && AkbankPos::class === $dispatchedEvent->getGatewayClass()
                && $txType === $dispatchedEvent->getTxType()
                && $paymentModel === $dispatchedEvent->getPaymentModel()
                && count($dispatchedEvent->getFormInputs()) > 3));

        $actual = $this->requestDataMapper->create3DFormData(
            $this->subMerchantAccount,
            $this->order,
            $paymentModel,
            $txType,
            'https://bank.com/pay',
            $card
        );

        unset($actual['inputs']['requestDateTime']);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(array $order, string $txType, array $responseData, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('generated-random-string');

        $actual = $this->requestDataMapper->create3DPaymentRequestData(
            $this->account,
            $order,
            $txType,
            $responseData
        );
        $this->assertArrayHasKey('requestDateTime', $actual);
        $this->assertSame(23, \strlen($actual['requestDateTime']));
        unset($actual['requestDateTime']);

        $this->assertSame($expected, $actual);
    }


    public function testCreate3DPaymentSubMerchantRequestData(): void
    {
        $expected = [
            'terminal'          => [
                'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                'terminalSafeId' => '2023090417500284633D137A249DBBEB',
            ],
            'subMerchant'       => [
                'subMerchantId' => 'sub-merchant-id',
            ],
            'version'           => '1.00',
            'txnCode'           => '1000',
            'randomNumber'      => 'generated-random-string',
            'order'             => [
                'orderId' => '2020110828BC',
            ],
            'transaction'       => [
                'amount'       => '1.10',
                'currencyCode' => 949,
                'motoInd'      => 0,
                'installCount' => 1,
            ],
            'secureTransaction' => [
                'secureId'      => 'VG8yV2tCRHpTSlpNN2VqcDJRS1k=',
                'secureEcomInd' => '02',
                'secureData'    => 'kBM8+wZGAAAAAAAAAAAAAAAAAAAA',
                'secureMd'      => '08A86B192287C69B2C443E7A42B29B5F46436C41DF8E159B4A232BB3D961940F',
            ],
            'customer'          => [
                'ipAddress' => '127.0.0.1',
            ],

        ];
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('generated-random-string');

        $actual = $this->requestDataMapper->create3DPaymentRequestData(
            $this->subMerchantAccount,
            $this->order,
            PosInterface::TX_TYPE_PAY_AUTH,
            AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData']
        );

        unset($actual['requestDateTime']);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, string $txType, array $expectedData): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expectedData['randomNumber']);

        $actualData = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            $order,
            $txType,
            $this->card
        );

        $this->assertSame(23, \strlen($actualData['requestDateTime']));
        unset($actualData['requestDateTime']);

        ksort($expectedData);
        ksort($actualData);

        $this->assertSame($expectedData, $actualData);
    }


    /**
     * @dataProvider nonSecurePaymentPostRequestDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expectedData): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expectedData['randomNumber']);

        $actualData = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);
        $this->assertSame(23, \strlen($actualData['requestDateTime']));
        unset($actualData['requestDateTime']);

        ksort($expectedData);
        ksort($actualData);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider cancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expectedData): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expectedData['randomNumber']);

        $actualData = $this->requestDataMapper->createCancelRequestData($this->account, $order);
        $this->assertSame(23, \strlen($actualData['requestDateTime']));
        unset($actualData['requestDateTime']);

        ksort($expectedData);
        ksort($actualData);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expectedData): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expectedData['randomNumber']);

        $actualData = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);
        $this->assertSame(23, \strlen($actualData['requestDateTime']));
        unset($actualData['requestDateTime']);

        ksort($expectedData);
        ksort($actualData);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider orderHistoryRequestDataProvider
     */
    public function testCreateOrderHistoryRequestData(array $order, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['randomNumber']);

        $actualData = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);
        $this->assertSame(23, \strlen($actualData['requestDateTime']));
        unset($actualData['requestDateTime']);

        $this->assertSame($expected, $actualData);
    }

    /**
     * @dataProvider historyRequestDataProvider
     */
    public function testCreateHistoryRequestData(array $data, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['randomNumber']);

        $actualData = $this->requestDataMapper->createHistoryRequestData($this->account, $data);
        ksort($actualData);
        ksort($expected);
        $this->assertSame($expected, $actualData);
    }

    public function testCreateStatusRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createStatusRequestData($this->account, []);
    }

    /**
     * @dataProvider createCustomQueryRequestDataDataProvider
     */
    public function testCreateCustomQueryRequestData(array $requestData, array $expectedData): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expectedData['randomNumber']);

        $actual = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);
        $this->assertSame(23, \strlen($actual['requestDateTime']));
        unset($actual['requestDateTime']);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data_link_creation_request' => [
            'request_data' => [
                'txnCode'     => '1020',
                'order'       => [
                    'orderTrackId' => 'ae15a6c8-467e-45de-b24c-b98821a42667',
                ],
                'payByLink'   => [
                    'linkTxnCode'       => '3000',
                    'linkTransferType'  => 'SMS',
                    'mobilePhoneNumber' => '5321234567',
                ],
                'transaction' => [
                    'amount'       => 1.00,
                    'currencyCode' => 949,
                    'motoInd'      => 0,
                    'installCount' => 1,
                ],
            ],
            'expected'     => [
                'version'      => '1.00',
                'txnCode'      => '1020',
                'randomNumber' => 'rand_134',
                'terminal'     => [
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                ],
                'order'        => [
                    'orderTrackId' => 'ae15a6c8-467e-45de-b24c-b98821a42667',
                ],
                'payByLink'    => [
                    'linkTxnCode'       => '3000',
                    'linkTransferType'  => 'SMS',
                    'mobilePhoneNumber' => '5321234567',
                ],
                'transaction'  => [
                    'amount'       => 1.00,
                    'currencyCode' => 949,
                    'motoInd'      => 0,
                    'installCount' => 1,
                ],
            ],
        ];

        yield 'with_account_data' => [
            'request_data' => [
                'version'         => 'VERSION_11',
                'txnCode'         => '1020',
                'requestDateTime' => '2024-04-14T16:45:30.000',
                'randomNumber'    => 'random_123',
                'terminal'        => [
                    'merchantSafeId' => 'MERCH_SAFE_ID',
                    'terminalSafeId' => 'TERM_SAFE_ID',
                ],
                'order'           => [
                    'orderTrackId' => 'ae15a6c8-467e-45de-b24c-b98821a42667',
                ],
                'payByLink'       => [
                    'linkTxnCode'       => '3000',
                    'linkTransferType'  => 'SMS',
                    'mobilePhoneNumber' => '5321234567',
                ],
                'transaction'     => [
                    'amount'       => 1.00,
                    'currencyCode' => 949,
                    'motoInd'      => 0,
                    'installCount' => 1,
                ],
            ],
            'expected'     => [
                'version'      => 'VERSION_11',
                'txnCode'      => '1020',
                'randomNumber' => 'random_123',
                'terminal'     => [
                    'merchantSafeId' => 'MERCH_SAFE_ID',
                    'terminalSafeId' => 'TERM_SAFE_ID',
                ],
                'order'        => [
                    'orderTrackId' => 'ae15a6c8-467e-45de-b24c-b98821a42667',
                ],
                'payByLink'    => [
                    'linkTxnCode'       => '3000',
                    'linkTransferType'  => 'SMS',
                    'mobilePhoneNumber' => '5321234567',
                ],
                'transaction'  => [
                    'amount'       => 1.00,
                    'currencyCode' => 949,
                    'motoInd'      => 0,
                    'installCount' => 1,
                ],
            ],
        ];
    }

    public static function historyRequestDataProvider(): array
    {
        return [
            'with_batch_number' => [
                'order'    => [
                    'batch_num' => 39,
                ],
                'expected' => [
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'report'       => [
                        'batchNumber' => 39,
                    ],
                    'randomNumber' => '128-character-random-string',
                ],
            ],
            'with_date_range'   => [
                'order'    => [
                    'start_date' => new \DateTimeImmutable('2024-04-13 13:00:00'),
                    'end_date'   => new \DateTimeImmutable('2024-04-14 13:00:00'),
                ],
                'expected' => [
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'report'       => [
                        'startDateTime' => '2024-04-13T13:00:00.000',
                        'endDateTime'   => '2024-04-14T13:00:00.000',
                    ],
                    'randomNumber' => '128-character-random-string',
                ],
            ],
        ];
    }

    public static function cancelRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id' => '2020110828BC',
                ],
                'expected' => [
                    'randomNumber' => '128-character-random-string',
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1003',
                    'order'        => [
                        'orderId' => '2020110828BC',
                    ],
                ],
            ],
            'recurring_single_fulfilled_tx'   => [
                'order'    => [
                    'recurring_id'                    => '2020110828BC',
                    'recurringOrderInstallmentNumber' => 2,
                ],
                'expected' => [
                    'randomNumber' => '128-character-random-string',
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1003',
                    'order'        => [
                        'orderTrackId' => '2020110828BC',
                    ],
                    'recurring'    => [
                        'recurringOrder' => 2,
                    ],
                ],
            ],
            'recurring_single_unfulfilled_tx' => [
                'order'    => [
                    'recurring_id'                    => '2020110828BC',
                    'recurringOrderInstallmentNumber' => 2,
                    'recurring_payment_is_pending'    => true,
                ],
                'expected' => [
                    'randomNumber' => '128-character-random-string',
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1013',
                    'order'        => [
                        'orderTrackId' => '2020110828BC',
                    ],
                    'recurring'    => [
                        'recurringOrder' => 2,
                    ],
                ],
            ],
            'recurring_all_txs'               => [
                'order'    => [
                    'recurring_id'                    => '2020110828BC',
                    'recurringOrderInstallmentNumber' => null,
                ],
                'expected' => [
                    'randomNumber' => '128-character-random-string',
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1013',
                    'order'        => [
                        'orderTrackId' => '2020110828BC',
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
                    'id'       => '2020110828BC',
                    'amount'   => 1.02,
                    'currency' => PosInterface::CURRENCY_TRY,
                ],
                'txType'   => PosInterface::TX_TYPE_REFUND,
                'expected' => [
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1002',
                    'randomNumber' => '128-character-random-string',
                    'order'        => [
                        'orderId' => '2020110828BC',
                    ],
                    'transaction'  => [
                        'amount'       => '1.02',
                        'currencyCode' => 949,
                    ],
                ],
            ],
            'recurring' => [
                'order'    => [
                    'recurring_id'                    => '2020110828BC',
                    'amount'                          => 1.02,
                    'currency'                        => PosInterface::CURRENCY_TRY,
                    'recurringOrderInstallmentNumber' => 2,
                ],
                'txType'   => PosInterface::TX_TYPE_REFUND,
                'expected' => [
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'randomNumber' => '128-character-random-string',
                    'order'        => [
                        'orderTrackId' => '2020110828BC',
                    ],
                    'transaction'  => [
                        'amount'       => '1.02',
                        'currencyCode' => 949,
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1002',
                    'recurring'    => [
                        'recurringOrder' => 2,
                    ],
                ],
            ],
        ];
    }

    public static function nonSecurePaymentRequestDataProvider(): array
    {
        return [
            'pay_no_installment'   => [
                'order'    => [
                    'id'          => '2020110828BC',
                    'amount'      => 1.10,
                    'ip'          => '127.0.0.1',
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                ],
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => [
                    'randomNumber' => '128-character-random-string',
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1000',
                    'card'         => [
                        'cardNumber' => '5555444433332222',
                        'cvv2'       => '123',
                        'expireDate' => '0122',
                    ],
                    'order'        => [
                        'orderId' => '2020110828BC',
                    ],
                    'transaction'  => [
                        'amount'       => '1.10',
                        'currencyCode' => 949,
                        'motoInd'      => 0,
                        'installCount' => 1,
                    ],
                    'customer'     => [
                        'ipAddress' => '127.0.0.1',
                    ],
                ],
            ],
            'pay_with_installment' => [
                'order'    => [
                    'id'          => '2020110828BC',
                    'amount'      => 1.10,
                    'ip'          => '127.0.0.1',
                    'installment' => 2,
                    'currency'    => PosInterface::CURRENCY_TRY,
                ],
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => [
                    'randomNumber' => '128-character-random-string',
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1000',
                    'card'         => [
                        'cardNumber' => '5555444433332222',
                        'cvv2'       => '123',
                        'expireDate' => '0122',
                    ],
                    'order'        => [
                        'orderId' => '2020110828BC',
                    ],
                    'transaction'  => [
                        'amount'       => '1.10',
                        'currencyCode' => 949,
                        'motoInd'      => 0,
                        'installCount' => 2,
                    ],
                    'customer'     => [
                        'ipAddress' => '127.0.0.1',
                    ],
                ],
            ],
            'pre_pay'              => [
                'order'    => [
                    'id'          => '2020110828BC',
                    'amount'      => 1.10,
                    'ip'          => '127.0.0.1',
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                ],
                'txType'   => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'expected' => [
                    'randomNumber' => '128-character-random-string',
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1004',
                    'card'         => [
                        'cardNumber' => '5555444433332222',
                        'cvv2'       => '123',
                        'expireDate' => '0122',
                    ],
                    'order'        => [
                        'orderId' => '2020110828BC',
                    ],
                    'transaction'  => [
                        'amount'       => '1.10',
                        'currencyCode' => 949,
                        'motoInd'      => 0,
                        'installCount' => 1,
                    ],
                    'customer'     => [
                        'ipAddress' => '127.0.0.1',
                    ],
                ],
            ],
            'pay_recurring'        => [
                'order'    => [
                    'id'        => '2020110828BC',
                    'amount'    => 1.10,
                    'ip'        => '127.0.0.1',
                    'recurring' => [
                        'frequency'     => 1,
                        'frequencyType' => 'MONTH',
                        'installment'   => 4,
                    ],
                    'currency'  => PosInterface::CURRENCY_TRY,
                ],
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => [
                    'randomNumber' => '128-character-random-string',
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1000',
                    'card'         => [
                        'cardNumber' => '5555444433332222',
                        'cvv2'       => '123',
                        'expireDate' => '0122',
                    ],
                    'order'        => [
                        'orderTrackId' => '2020110828BC',
                    ],
                    'transaction'  => [
                        'amount'       => '1.10',
                        'currencyCode' => 949,
                        'motoInd'      => 1,
                        'installCount' => 1,
                    ],
                    'recurring'    => [
                        'frequencyInterval' => 1,
                        'frequencyCycle'    => 'M',
                        'numberOfPayments'  => 4,
                    ],
                    'customer'     => [
                        'ipAddress' => '127.0.0.1',
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
                    'id'       => '2020110828BC',
                    'amount'   => 1.10,
                    'ip'       => '127.0.0.1',
                    'currency' => PosInterface::CURRENCY_TRY,
                ],
                'expected' => [
                    'randomNumber' => '128-character-random-string',
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1005',
                    'order'        => [
                        'orderId' => '2020110828BC',
                    ],
                    'transaction'  => [
                        'amount'       => '1.10',
                        'currencyCode' => 949,
                    ],
                    'customer'     => [
                        'ipAddress' => '127.0.0.1',
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
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1010',
                    'randomNumber' => '128-character-random-string',
                    'order'        => [
                        'orderId' => '2020110828BC',
                    ],
                ],
            ],
            'recurring_order' => [
                'order'    => [
                    'recurring_id' => '2020110828BC',
                ],
                'expected' => [
                    'terminal'     => [
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                    ],
                    'version'      => '1.00',
                    'txnCode'      => '1010',
                    'randomNumber' => '128-character-random-string',
                    'order'        => [
                        'orderTrackId' => '2020110828BC',
                    ],
                ],
            ],
        ];
    }

    public static function threeDFormDataProvider(): array
    {
        return [
            '3d_host_form_data'    => [
                'order'         => [
                    'id'          => '2020110828BC',
                    'amount'      => 10,
                    'ip'          => '127.0.0.1',
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'success_url' => 'http:://localhost/success',
                    'fail_url'    => 'http:://localhost/fail',
                ],
                'tx_type'       => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model' => PosInterface::MODEL_3D_HOST,
                'is_with_card'  => false,
                'gateway'       => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                'expected'      => [
                    'gateway' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                    'method'  => 'POST',
                    'inputs'  => [
                        'paymentModel'   => '3D_PAY_HOSTING',
                        'txnCode'        => '3000',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'orderId'        => '2020110828BC',
                        'lang'           => 'TR',
                        'amount'         => '10.00',
                        'currencyCode'   => '949',
                        'installCount'   => '1',
                        'okUrl'          => 'http:://localhost/success',
                        'failUrl'        => 'http:://localhost/fail',
                        'randomNumber'   => '128-character-random-string',
                        'hash'           => 'hash-123',
                    ],
                ],
            ],
            '3d_pay_form_data'     => [
                'order'         => [
                    'id'          => '2020110828BC',
                    'amount'      => 1.1,
                    'ip'          => '127.0.0.1',
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'success_url' => 'http:://localhost/success',
                    'fail_url'    => 'http:://localhost/fail',
                ],
                'tx_type'       => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model' => PosInterface::MODEL_3D_PAY,
                'is_with_card'  => true,
                'gateway'       => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                'expected'      => [
                    'gateway' => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                    'method'  => 'POST',
                    'inputs'  => [
                        'paymentModel'   => '3D_PAY',
                        'txnCode'        => '3000',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'orderId'        => '2020110828BC',
                        'lang'           => 'TR',
                        'amount'         => '1.10',
                        'currencyCode'   => '949',
                        'installCount'   => '1',
                        'okUrl'          => 'http:://localhost/success',
                        'failUrl'        => 'http:://localhost/fail',
                        'randomNumber'   => '128-character-random-string',
                        'creditCard'     => '5555444433332222',
                        'expiredDate'    => '0122',
                        'cvv'            => '123',
                        'hash'           => 'hash-123',
                    ],
                ],
            ],
            '3d_form_data'         => [
                'order'         => [
                    'id'          => '2020110828BC',
                    'amount'      => 1.1,
                    'ip'          => '127.0.0.1',
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'success_url' => 'http:://localhost/success',
                    'fail_url'    => 'http:://localhost/fail',
                ],
                'tx_type'       => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model' => PosInterface::MODEL_3D_SECURE,
                'is_with_card'  => true,
                'gateway'       => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                'expected'      => [
                    'gateway' => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                    'method'  => 'POST',
                    'inputs'  => [
                        'paymentModel'   => '3D',
                        'txnCode'        => '3000',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'orderId'        => '2020110828BC',
                        'lang'           => 'TR',
                        'amount'         => '1.10',
                        'currencyCode'   => '949',
                        'installCount'   => '1',
                        'okUrl'          => 'http:://localhost/success',
                        'failUrl'        => 'http:://localhost/fail',
                        'randomNumber'   => '128-character-random-string',
                        'creditCard'     => '5555444433332222',
                        'expiredDate'    => '0122',
                        'cvv'            => '123',
                        'hash'           => 'hash-123',
                    ],
                ],
            ],
            '3d_pre_pay_form_data' => [
                'order'         => [
                    'id'          => '2020110828BC',
                    'amount'      => 1000,
                    'ip'          => '127.0.0.1',
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'success_url' => 'http:://localhost/success',
                    'fail_url'    => 'http:://localhost/fail',
                ],
                'tx_type'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'payment_model' => PosInterface::MODEL_3D_SECURE,
                'is_with_card'  => true,
                'gateway'       => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                'expected'      => [
                    'gateway' => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                    'method'  => 'POST',
                    'inputs'  => [
                        'paymentModel'   => '3D',
                        'txnCode'        => '3004',
                        'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                        'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                        'orderId'        => '2020110828BC',
                        'lang'           => 'TR',
                        'amount'         => '1000.00',
                        'currencyCode'   => '949',
                        'installCount'   => '1',
                        'okUrl'          => 'http:://localhost/success',
                        'failUrl'        => 'http:://localhost/fail',
                        'randomNumber'   => '128-character-random-string',
                        'creditCard'     => '5555444433332222',
                        'expiredDate'    => '0122',
                        'cvv'            => '123',
                        'hash'           => 'hash-123',
                    ],
                ],
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
            'responseData' => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData'],
            'expected'     => [
                'terminal'          => [
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                ],
                'version'           => '1.00',
                'txnCode'           => '1000',
                'randomNumber'      => 'generated-random-string',
                'order'             => [
                    'orderId' => '2020110828BC',
                ],
                'transaction'       => [
                    'amount'       => '100.25',
                    'currencyCode' => 949,
                    'motoInd'      => 0,
                    'installCount' => 1,
                ],
                'secureTransaction' => [
                    'secureId'      => 'VG8yV2tCRHpTSlpNN2VqcDJRS1k=',
                    'secureEcomInd' => '02',
                    'secureData'    => 'kBM8+wZGAAAAAAAAAAAAAAAAAAAA',
                    'secureMd'      => '08A86B192287C69B2C443E7A42B29B5F46436C41DF8E159B4A232BB3D961940F',
                ],
                'customer'          => [
                    'ipAddress' => '156.155.154.153',
                ],
            ],
        ];
    }
}

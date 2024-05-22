<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use InvalidArgumentException;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper
 */
class PosNetV1PosRequestDataMapperTest extends TestCase
{
    private CreditCardInterface $card;

    private PosNetV1PosRequestDataMapper $requestDataMapper;

    private PosNetAccount $account;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPosNetAccount(
            'albaraka',
            '6700950031',
            '67540050',
            '1010028724242434',
            PosInterface::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->card = CreditCardFactory::create('5400619360964581', '20', '01', '056', 'ahmet');

        $crypt                   = CryptFactory::createGatewayCrypt(PosNetV1Pos::class, new NullLogger());
        $this->requestDataMapper = new PosNetV1PosRequestDataMapper($this->dispatcher, $crypt);
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
     * @testWith ["0", "0"]
     *           ["1", "0"]
     *           ["2", "2"]
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
        $this->assertSame('TDS_00000000000000000010', $this->requestDataMapper::mapOrderIdToPrefixedOrderId(10, PosInterface::MODEL_3D_SECURE));
        $this->assertSame('000000000000000000000010', $this->requestDataMapper::mapOrderIdToPrefixedOrderId(10, PosInterface::MODEL_3D_PAY));
        $this->assertSame('000000000000000000000010', $this->requestDataMapper::mapOrderIdToPrefixedOrderId(10, PosInterface::MODEL_NON_SECURE));
    }

    /**
     * @return void
     */
    public function testFormatOrderId(): void
    {
        $this->assertSame('0010', $this->requestDataMapper::formatOrderId(10, 4));
        $this->assertSame('12345', $this->requestDataMapper::formatOrderId(12345, 5));
        $this->assertSame('123456789012345566fm', $this->requestDataMapper::formatOrderId('123456789012345566fm'));
    }

    /**
     * @return void
     */
    public function testFormatOrderIdFail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->requestDataMapper::formatOrderId('123456789012345566fml');
    }

    /**
     * @dataProvider nonSecurePostPaymentDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account,$order, PosInterface::TX_TYPE_PAY_AUTH, $this->card);

        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider create3DPaymentRequestDataProvider
     */
    public function testCreate3DPaymentRequestData(array $order, string $txType, array $responseData, array $expectedData): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $responseData);

        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider threeDFormDataTestProvider
     */
    public function testCreate3DFormData(array $order, string $txType, string $gatewayUrl, array $expected): void
    {
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with($this->callback(function ($dispatchedEvent) use ($txType, $paymentModel) {
                return $dispatchedEvent instanceof Before3DFormHashCalculatedEvent
                    && PosNetV1Pos::class === $dispatchedEvent->getGatewayClass()
                    && $txType === $dispatchedEvent->getTxType()
                    && $paymentModel === $dispatchedEvent->getPaymentModel()
                    && count($dispatchedEvent->getFormInputs()) > 3;
            }));

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayUrl,
            $this->card
        );

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider createStatusRequestDataDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createStatusRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider createRefundRequestDataDataProvider
     */
    public function testCreateRefundRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @dataProvider createCancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array
     */
    public static function threeDFormDataTestProvider(): iterable
    {
        $order      = [
            'id'          => '620093100_024',
            'amount'      => 1.75,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'lang'        => PosInterface::LANG_TR,
        ];
        $gatewayUrl = 'https://epostest.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx';
        yield [
            'order'      => $order,
            'txType'     => PosInterface::TX_TYPE_PAY_AUTH,
            'gatewayUrl' => $gatewayUrl,
            'expected'   => [
                'inputs'  => [
                    'MerchantNo'        => '6700950031',
                    'TerminalNo'        => '67540050',
                    'PosnetID'          => '1010028724242434',
                    'TransactionType'   => 'Sale',
                    'OrderId'           => '0000000620093100_024',
                    'Amount'            => '175',
                    'CurrencyCode'      => 'TL',
                    'MerchantReturnURL' => 'https://domain.com/success',
                    'InstallmentCount'  => '0',
                    'Language'          => 'tr',
                    'TxnState'          => 'INITIAL',
                    'OpenNewWindow'     => '0',
                    'CardNo'            => '5400619360964581',
                    'ExpiredDate'       => '2001',
                    'Cvv'               => '056',
                    'CardHolderName'    => 'ahmet',
                    'MacParams'         => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
                    'UseOOS'            => '0',
                    'Mac'               => 'xuhPbpcPJ6kVs7JeIXS8f06Cv0mb9cNPMfjp1HiB7Ew=',
                ],
                'method'  => 'POST',
                'gateway' => $gatewayUrl,
            ],
        ];
    }

    public static function nonSecurePaymentRequestDataDataProvider(): iterable
    {
        yield [
            'order' => [
                'id'          => '123',
                'amount'      => 10.0,
                'installment' => 0,
                'currency'    => PosInterface::CURRENCY_TRY,
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MACParams'              => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'AdditionalInfoData'     => null,
                'CardInformationData'    => [
                    'CardNo'         => '5400619360964581',
                    'ExpireDate'     => '2001',
                    'Cvc2'           => '056',
                    'CardHolderName' => 'ahmet',
                ],
                'IsMailOrder'            => 'N',
                'IsRecurring'            => null,
                'IsTDSecureMerchant'     => null,
                'PaymentInstrumentType'  => 'CARD',
                'ThreeDSecureData'       => null,
                'Amount'                 => 1000,
                'CurrencyCode'           => 'TL',
                'OrderId'                => '00000000000000000123',
                'InstallmentCount'       => '0',
                'InstallmentType'        => 'N',
                'KOICode'                => null,
                'MerchantMessageData'    => null,
                'PointAmount'            => null,
                'MAC'                    => '/R6nxI0N73nxANbkq4JVI3h94/mE0htExYyszlqTTWM=',
            ],
        ];

        yield 'withInstallment' => [
            'order'    => [
                'id'          => '123',
                'amount'      => 10.0,
                'currency'    => PosInterface::CURRENCY_TRY,
                'installment' => 3,
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MACParams'              => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'AdditionalInfoData'     => null,
                'CardInformationData'    => [
                    'CardNo'         => '5400619360964581',
                    'ExpireDate'     => '2001',
                    'Cvc2'           => '056',
                    'CardHolderName' => 'ahmet',
                ],
                'IsMailOrder'            => 'N',
                'IsRecurring'            => null,
                'IsTDSecureMerchant'     => null,
                'PaymentInstrumentType'  => 'CARD',
                'ThreeDSecureData'       => null,
                'Amount'                 => 1000,
                'CurrencyCode'           => 'TL',
                'OrderId'                => '00000000000000000123',
                'InstallmentCount'       => '3',
                'InstallmentType'        => 'Y',
                'KOICode'                => null,
                'MerchantMessageData'    => null,
                'PointAmount'            => null,
                'MAC'                    => '/R6nxI0N73nxANbkq4JVI3h94/mE0htExYyszlqTTWM=',
            ],
        ];
    }

    public static function nonSecurePostPaymentDataProvider(): iterable
    {
        yield [
            'order'    => [
                'id'          => '123',
                'installment' => 0,
                'amount'      => 12.3,
                'currency'    => PosInterface::CURRENCY_TRY,
                'ref_ret_num' => '159044932490000231',
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MACParams'              => 'MerchantNo:TerminalNo',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'Amount'                 => 1230,
                'CurrencyCode'           => 'TL',
                'ReferenceCode'          => '159044932490000231',
                'InstallmentCount'       => '0',
                'InstallmentType'        => 'N',
                'MAC'                    => 'wgyfAJPbEPtTtce/+HRlXajSRfYA0J6mUcH+16EbB78=',
            ],
        ];
    }

    public static function create3DPaymentRequestDataProvider(): \Generator
    {
        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
        ];
        yield [
            'order'        => $order,
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => [
                'SecureTransactionId' => '1010028947569644',
                'CAVV'                => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                'ECI'                 => '02',
                'MdStatus'            => '1',
                'MD'                  => '9998F61E1D0C0FB6EC5203A748124F30',
            ],
            'expected'     => [
                'ApiType'               => 'JSON',
                'ApiVersion'            => 'V100',
                'MerchantNo'            => '6700950031',
                'TerminalNo'            => '67540050',
                'PaymentInstrumentType' => 'CARD',
                'IsEncrypted'           => 'N',
                'IsTDSecureMerchant'    => 'Y',
                'IsMailOrder'           => 'N',
                'ThreeDSecureData'      => [
                    'SecureTransactionId' => '1010028947569644',
                    'CavvData'            => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                    'Eci'                 => '02',
                    'MdStatus'            => 1,
                    'MD'                  => '9998F61E1D0C0FB6EC5203A748124F30',
                ],
                'MACParams'             => 'MerchantNo:TerminalNo:SecureTransactionId:CavvData:Eci:MdStatus',
                'Amount'                => 10001,
                'CurrencyCode'          => 'TL',
                'PointAmount'           => 0,
                'OrderId'               => '000000002020110828BC',
                'InstallmentCount'      => '0',
                'InstallmentType'       => 'N',
                'MAC'                   => 'kAKxvbwXvmrM6lapGx1UcRTs454tsSuPrBXV7oA7L7w=',
            ],
        ];
    }

    public static function createRefundRequestDataDataProvider(): iterable
    {
        yield 'withOrderIdAndReferenceCode' => [
            'order'    => [
                'id'               => '000000002020110828BC',
                'ref_ret_num'      => '159044932490000231',
                'amount'           => 112,
                'payment_model'    => PosInterface::MODEL_3D_SECURE,
                'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
                'MAC'                    => 'Rp/jX8D1FM+DF/Bq49MgQDYuBdzTExy+8qN7jwO9ZYI=',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'ReferenceCode'          => '159044932490000231',
                'OrderId'                => null,
                'TransactionType'        => 'Sale',
            ],
        ];

        yield 'withOrderId' => [
            'order'    => [
                'id'               => '000000002020110828BC',
                'amount'           => 112,
                'payment_model'    => PosInterface::MODEL_3D_SECURE,
                'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
                'MAC'                    => '/RdhuykGKN/DPHXTg0Cwn6aHnAwqmH8OUAwuISKg4bc=',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'ReferenceCode'          => null,
                'OrderId'                => 'TDS_000000002020110828BC',
                'TransactionType'        => 'Sale',
            ],
        ];

        yield 'withReferenceCode' => [
            'order'    => [
                'ref_ret_num'      => '159044932490000231',
                'amount'           => 112,
                'payment_model'    => PosInterface::MODEL_3D_SECURE,
                'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
                'MAC'                    => 'Rp/jX8D1FM+DF/Bq49MgQDYuBdzTExy+8qN7jwO9ZYI=',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'ReferenceCode'          => '159044932490000231',
                'OrderId'                => null,
                'TransactionType'        => 'Sale',
            ],
        ];

        yield 'cancelPrePay' => [
            'order'    => [
                'ref_ret_num'      => '159044932490000231',
                'payment_model'    => PosInterface::MODEL_3D_SECURE,
                'amount'           => 112,
                'transaction_type' => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
                'MAC'                    => 'Rp/jX8D1FM+DF/Bq49MgQDYuBdzTExy+8qN7jwO9ZYI=',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'ReferenceCode'          => '159044932490000231',
                'OrderId'                => null,
                'TransactionType'        => 'Auth',
            ],
        ];
    }

    public static function createCancelRequestDataProvider(): iterable
    {
        yield 'withOrderIdAndReferenceCode' => [
            'order'    => [
                'id'               => '000000002020110828BC',
                'ref_ret_num'      => '159044932490000231',
                'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
                'MAC'                    => 'Rp/jX8D1FM+DF/Bq49MgQDYuBdzTExy+8qN7jwO9ZYI=',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'ReferenceCode'          => '159044932490000231',
                'OrderId'                => null,
                'TransactionType'        => 'Sale',
            ],
        ];

        yield 'withOrderId' => [
            'order'    => [
                'id'               => '000000002020110828BC',
                'payment_model'    => PosInterface::MODEL_3D_SECURE,
                'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
                'MAC'                    => '/RdhuykGKN/DPHXTg0Cwn6aHnAwqmH8OUAwuISKg4bc=',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'ReferenceCode'          => null,
                'OrderId'                => 'TDS_000000002020110828BC',
                'TransactionType'        => 'Sale',
            ],
        ];

        yield 'withReferenceCode' => [
            'order'    => [
                'ref_ret_num'      => '159044932490000231',
                'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
                'MAC'                    => 'Rp/jX8D1FM+DF/Bq49MgQDYuBdzTExy+8qN7jwO9ZYI=',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'ReferenceCode'          => '159044932490000231',
                'OrderId'                => null,
                'TransactionType'        => 'Sale',
            ],
        ];

        yield 'cancelPrePay' => [
            'order'    => [
                'ref_ret_num'      => '159044932490000231',
                'transaction_type' => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
                'MAC'                    => 'Rp/jX8D1FM+DF/Bq49MgQDYuBdzTExy+8qN7jwO9ZYI=',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'ReferenceCode'          => '159044932490000231',
                'OrderId'                => null,
                'TransactionType'        => 'Auth',
            ],
        ];
    }

    public static function createStatusRequestDataDataProvider(): iterable
    {
        yield 'withOrderIdAndReferenceCode' => [
            'order'    => [
                'id'            => '000000002020110828BC',
                'payment_model' => PosInterface::MODEL_3D_SECURE,
            ],
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'MACParams'              => 'MerchantNo:TerminalNo',
                'MAC'                    => 'wgyfAJPbEPtTtce/+HRlXajSRfYA0J6mUcH+16EbB78=',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => 'N',
                'PaymentFacilitatorData' => null,
                'OrderId'                => 'TDS_000000002020110828BC',
            ],
        ];
    }
}

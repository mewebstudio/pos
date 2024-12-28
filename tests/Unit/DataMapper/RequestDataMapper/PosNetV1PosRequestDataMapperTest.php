<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use InvalidArgumentException;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class PosNetV1PosRequestDataMapperTest extends TestCase
{
    private CreditCardInterface $card;

    private PosNetV1PosRequestDataMapper $requestDataMapper;

    private PosNetAccount $account;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

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

        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new PosNetV1PosRequestDataMapper($this->dispatcher, $this->crypt);
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
        $hashCalculationData = $expectedData;
        unset($hashCalculationData['MAC']);

        $this->crypt->expects(self::once())
            ->method('hashFromParams')
            ->with($this->account->getStoreKey(), $hashCalculationData, 'MACParams', ':')
            ->willReturn($expectedData['MAC']);

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, array $expectedData): void
    {
        $hashCalculationData        = $expectedData;
        unset($hashCalculationData['MAC']);

        $this->crypt->expects(self::once())
            ->method('hashFromParams')
            ->with($this->account->getStoreKey(), $hashCalculationData, 'MACParams', ':')
            ->willReturn($expectedData['MAC']);

        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, PosInterface::TX_TYPE_PAY_AUTH, $this->card);

        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider create3DPaymentRequestDataProvider
     */
    public function testCreate3DPaymentRequestData(array $order, string $txType, array $responseData, array $expectedData): void
    {
        $hashCalculationData        = $expectedData;
        unset($hashCalculationData['MAC']);

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expectedData['MAC']);

        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $responseData);

        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider threeDFormDataTestProvider
     */
    public function testCreate3DFormData(array $order, string $txType, string $gatewayUrl, ?CreditCardInterface $card, array $expected): void
    {
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with($this->callback(static fn ($dispatchedEvent): bool => $dispatchedEvent instanceof Before3DFormHashCalculatedEvent
                && PosNetV1Pos::class === $dispatchedEvent->getGatewayClass()
                && $txType === $dispatchedEvent->getTxType()
                && $paymentModel === $dispatchedEvent->getPaymentModel()
                && count($dispatchedEvent->getFormInputs()) > 3));

        $hashCalculationData        = $expected['inputs'];
        unset($hashCalculationData['Mac']);

        $this->crypt->expects(self::once())
            ->method('create3DHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expected['inputs']['Mac']);

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayUrl,
            $card
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createStatusRequestDataDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected): void
    {
        $hashCalculationData        = $expected;
        unset($hashCalculationData['MAC']);

        $this->crypt->expects(self::once())
            ->method('hashFromParams')
            ->with($this->account->getStoreKey(), $hashCalculationData, 'MACParams', ':')
            ->willReturn($expected['MAC']);

        $actual = $this->requestDataMapper->createStatusRequestData($this->account, $order);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createRefundRequestDataDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expected): void
    {
        $hashCalculationData        = $expected;
        unset($hashCalculationData['MAC']);

        $this->crypt->expects(self::once())
            ->method('hashFromParams')
            ->with($this->account->getStoreKey(), $hashCalculationData, 'MACParams', ':')
            ->willReturn($expected['MAC']);

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        ksort($actual);
        ksort($expected);
        $this->assertSame($expected, $actual);
    }


    /**
     * @dataProvider createCancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected): void
    {
        $hashCalculationData        = $expected;
        unset($hashCalculationData['MAC']);

        $this->crypt->expects(self::once())
            ->method('hashFromParams')
            ->with($this->account->getStoreKey(), $hashCalculationData, 'MACParams', ':')
            ->willReturn($expected['MAC']);

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        ksort($actual);
        ksort($expected);
        $this->assertSame($expected, $actual);
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

    /**
     * @dataProvider createCustomQueryRequestDataDataProvider
     */
    public function testCreateCustomQueryRequestData(array $requestData, array $expectedData): void
    {
        if (!isset($requestData['MAC'])) {
            $this->crypt->expects(self::once())
                ->method('hashFromParams')
                ->willReturn($expectedData['MAC']);
        }

        $actual = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data_point_inquiry' => [
            'request_data' => [
                'MACParams'             => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate',
                'CipheredData'          => null,
                'DealerData'            => null,
                'IsEncrypted'           => 'N',
                'PaymentFacilitatorData' => null,
                'AdditionalInfoData'    => null,
                'CardInformationData'   => [
                    'CardHolderName' => 'deneme deneme',
                    'CardNo'         => '5400619360964581',
                    'Cvc2'           => '056',
                    'ExpireDate'     => '2001',
                ],
                'IsMailOrder'           => null,
                'IsRecurring'           => null,
                'IsTDSecureMerchant'    => 'Y',
                'PaymentInstrumentType' => 'CARD',
                'ThreeDSecureData'      => null,
            ],
            'expected'     => [
                'AdditionalInfoData'     => null,
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'CardInformationData'    => [
                    'CardHolderName' => 'deneme deneme',
                    'CardNo'         => '5400619360964581',
                    'Cvc2'           => '056',
                    'ExpireDate'     => '2001',
                ],
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => 'N',
                'IsMailOrder'            => null,
                'IsRecurring'            => null,
                'IsTDSecureMerchant'     => 'Y',
                'MAC'                    => 'jlksjfjldsf',
                'MACParams'              => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate',
                'MerchantNo'             => '6700950031',
                'PaymentFacilitatorData' => null,
                'PaymentInstrumentType'  => 'CARD',
                'TerminalNo'             => '67540050',
                'ThreeDSecureData'       => null,
            ],
        ];

        yield 'with_account_data_point_inquiry' => [
            'request_data' => [
                'AdditionalInfoData'     => null,
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'CardInformationData'    => [
                    'CardHolderName' => 'deneme deneme',
                    'CardNo'         => '5400619360964581',
                    'Cvc2'           => '056',
                    'ExpireDate'     => '2001',
                ],
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => 'N',
                'IsMailOrder'            => null,
                'IsRecurring'            => null,
                'IsTDSecureMerchant'     => 'Y',
                'MAC'                    => 'jlksjfjldsfxxx',
                'MACParams'              => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate',
                'MerchantNo'             => '6700950031xxx',
                'TerminalNo'             => '67540050xxx',
                'PaymentFacilitatorData' => null,
                'PaymentInstrumentType'  => 'CARD',
                'ThreeDSecureData'       => null,
            ],
            'expected'     => [
                'AdditionalInfoData'     => null,
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'CardInformationData'    => [
                    'CardHolderName' => 'deneme deneme',
                    'CardNo'         => '5400619360964581',
                    'Cvc2'           => '056',
                    'ExpireDate'     => '2001',
                ],
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => 'N',
                'IsMailOrder'            => null,
                'IsRecurring'            => null,
                'IsTDSecureMerchant'     => 'Y',
                'MAC'                    => 'jlksjfjldsfxxx',
                'MACParams'              => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate',
                'MerchantNo'             => '6700950031xxx',
                'TerminalNo'             => '67540050xxx',
                'PaymentFacilitatorData' => null,
                'PaymentInstrumentType'  => 'CARD',
                'ThreeDSecureData'       => null,
            ],
        ];
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
        $card = CreditCardFactory::create('5400619360964581', '20', '01', '056', 'ahmet');

        $gatewayUrl = 'https://epostest.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx';
        yield [
            'order'      => $order,
            'txType'     => PosInterface::TX_TYPE_PAY_AUTH,
            'gatewayUrl' => $gatewayUrl,
            'card'       => $card,
            'expected'   => [
                'gateway' => $gatewayUrl,
                'method'  => 'POST',
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
            ],
        ];

        yield '3d_host_order' => [
            'order'      => $order,
            'txType'     => PosInterface::TX_TYPE_PAY_AUTH,
            'gatewayUrl' => $gatewayUrl,
            'card'       => null,
            'expected'   => [
                'gateway' => $gatewayUrl,
                'method'  => 'POST',
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
                    'UseOOS'            => '1',
                    'MacParams'         => 'MerchantNo:TerminalNo:Amount',
                    'Mac'               => 'UBdwWJh9rBCM0YWkBti7vHZm2G+nag16hAguohNrq1Y=',
                ],
            ],
        ];
    }

    public static function nonSecurePaymentRequestDataDataProvider(): iterable
    {
        yield [
            'order'    => [
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

        yield 'with_installment' => [
            'order'    => [
                'id'          => '123',
                'installment' => 2,
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
                'InstallmentCount'       => '2',
                'InstallmentType'        => 'Y',
                'MAC'                    => 'wgyfAJPbEPtTtce/+HRlXajSRfYA0J6mUcH+16EbB78=',
            ],
        ];
    }

    public static function create3DPaymentRequestDataProvider(): \Generator
    {
        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => 0,
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

        $order['installment'] = 2;

        yield 'with_installment' => [
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
                'InstallmentCount'      => '2',
                'InstallmentType'       => 'Y',
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
            'tx_type'  => PosInterface::TX_TYPE_REFUND,
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

        yield 'refund_non_secure_order' => [
            'order'    => [
                'id'               => '000000002020110828BC',
                'amount'           => 112,
                'payment_model'    => PosInterface::MODEL_NON_SECURE,
                'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            'tx_type'  => PosInterface::TX_TYPE_REFUND,
            'expected' => [
                'ApiType'                => 'JSON',
                'ApiVersion'             => 'V100',
                'MerchantNo'             => '6700950031',
                'TerminalNo'             => '67540050',
                'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
                'MAC'                    => '9Ffy2cgMphKFSg2nyXr38gKXJhC8HL+L6X3KEkpt0AQ=',
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => null,
                'PaymentFacilitatorData' => null,
                'ReferenceCode'          => null,
                'OrderId'                => '0000000000002020110828BC',
                'TransactionType'        => 'Sale',
                'Amount'                 => 11200,
                'CurrencyCode'           => 'TL',
            ],
        ];

        yield 'withOrderId' => [
            'order'    => [
                'id'               => '000000002020110828BC',
                'amount'           => 112,
                'payment_model'    => PosInterface::MODEL_3D_SECURE,
                'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            'tx_type'  => PosInterface::TX_TYPE_REFUND,
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
            'tx_type'  => PosInterface::TX_TYPE_REFUND,
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
            'tx_type'  => PosInterface::TX_TYPE_REFUND,
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
                'CipheredData'           => null,
                'DealerData'             => null,
                'IsEncrypted'            => 'N',
                'PaymentFacilitatorData' => null,
                'OrderId'                => 'TDS_000000002020110828BC',
                'MAC'                    => 'wgyfAJPbEPtTtce/+HRlXajSRfYA0J6mUcH+16EbB78=',
            ],
        ];
    }
}

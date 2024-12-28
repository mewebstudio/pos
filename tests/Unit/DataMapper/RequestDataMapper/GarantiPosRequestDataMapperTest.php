<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\GarantiPosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class GarantiPosRequestDataMapperTest extends TestCase
{
    private GarantiPosAccount $account;

    private CreditCardInterface $card;

    private GarantiPosRequestDataMapper $requestDataMapper;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            PosInterface::MODEL_3D_SECURE,
            '12345678',
            'PROVRFN',
            '123qweASD/'
        );

        $this->dispatcher        = $this->createMock(EventDispatcherInterface::class);
        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new GarantiPosRequestDataMapper($this->dispatcher, $this->crypt);
        $this->requestDataMapper->setTestMode(true);

        $this->card = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet');
    }

    /**
     * @testWith ["pay", "sales"]
     * ["pre", "preauth"]
     */
    public function testMapTxType(string $txType, string $expected): void
    {
        $actual = $this->requestDataMapper->mapTxType($txType);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["sales"]
     */
    public function testMapTxTypeException(string $txType): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->requestDataMapper->mapTxType($txType);
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
     * @dataProvider nonSecurePaymentPostRequestDataDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expected): void
    {
        $hashCalculationData                         = $expected;
        $hashCalculationData['Terminal']['HashData'] = '';

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expected['Terminal']['HashData']);

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, string $txType, CreditCardInterface $card, array $expectedData): void
    {
        $hashCalculationData                         = $expectedData;
        $hashCalculationData['Terminal']['HashData'] = '';

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expectedData['Terminal']['HashData']);

        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            $order,
            $txType,
            $card
        );

        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider cancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expectedData): void
    {
        $hashCalculationData                         = $expectedData;
        $hashCalculationData['Terminal']['HashData'] = '';

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expectedData['Terminal']['HashData']);

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider orderHistoryRequestDataProvider
     */
    public function testCreateOrderHistoryRequestData(array $order, array $expectedData): void
    {
        $hashCalculationData                         = $expectedData;
        $hashCalculationData['Terminal']['HashData'] = '';

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expectedData['Terminal']['HashData']);

        $actual = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);

        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider historyRequestDataProvider
     */
    public function testCreateHistoryRequestData(array $data, array $expectedData): void
    {
        $hashCalculationData                         = $expectedData;
        $hashCalculationData['Terminal']['HashData'] = '';

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expectedData['Terminal']['HashData']);

        $actualData = $this->requestDataMapper->createHistoryRequestData($this->account, $data);

        \ksort($expectedData);
        \ksort($actualData);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(GarantiPosAccount $garantiPosAccount, array $order, array $responseData, array $expected): void
    {
        $hashCalculationData                         = $expected;
        $hashCalculationData['Terminal']['HashData'] = '';

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expected['Terminal']['HashData']);

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
        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with($this->callback(static fn ($dispatchedEvent): bool => $dispatchedEvent instanceof Before3DFormHashCalculatedEvent
                && GarantiPos::class === $dispatchedEvent->getGatewayClass()
                && $txType === $dispatchedEvent->getTxType()
                && $paymentModel === $dispatchedEvent->getPaymentModel()
                && count($dispatchedEvent->getFormInputs()) > 3));

        $hashCalculationData = $expected['inputs'];
        unset($hashCalculationData['secure3dhash']);

        $this->crypt->expects(self::once())
            ->method('create3DHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expected['inputs']['secure3dhash']);

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayURL,
            $isWithCard ? $this->card : null
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider statusRequestDataDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected): void
    {
        $hashCalculationData                         = $expected;
        $hashCalculationData['Terminal']['HashData'] = '';

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expected['Terminal']['HashData']);

        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $this->assertSame($expected, $actualData);
    }

    /**
     * @dataProvider refundOrderDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expected): void
    {
        $hashCalculationData                         = $expected;
        $hashCalculationData['Terminal']['HashData'] = '';

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expected['Terminal']['HashData']);

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public function testCreateRefundRequestDataWithoutRefundCredentials(): void
    {
        $account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            PosInterface::MODEL_3D_SECURE,
            '12345678',
        );
        $order         = [
            'id'          => '2020110828BC',
            'ip'          => '127.15.15.1',
            'currency'    => PosInterface::CURRENCY_TRY,
            'amount'      => 123.1,
            'ref_ret_num' => '831803579226',
            'installment' => 0,
        ];

        $this->expectException(\LogicException::class);
        $this->requestDataMapper->createRefundRequestData(
            $account,
            $order,
            PosInterface::TX_TYPE_REFUND
        );
    }

    /**
     * @dataProvider createCustomQueryRequestDataDataProvider
     */
    public function testCreateCustomQueryRequestData(array $requestData, array $expectedData): void
    {
        if (!isset($requestData['Terminal']['HashData'])) {
            $this->crypt->expects(self::once())
                ->method('createHash')
                ->willReturn($expectedData['Terminal']['HashData']);
        }

        $actual = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);

        \ksort($actual['Terminal']);
        \ksort($expectedData['Terminal']);
        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data_bin_inquiry' => [
            'request_data' => [
                'Version'     => 'v0.00',
                'Customer'    => [
                    'IPAddress'    => '1.1.111.111',
                    'EmailAddress' => 'Cem@cem.com',
                ],
                'Order'       => [
                    'OrderID'     => 'SISTD5A61F1682E745B28871872383ABBEB1',
                    'GroupID'     => '',
                    'Description' => '',
                ],
                'Transaction' => [
                    'Type'   => 'bininq',
                    'Amount' => '1',
                    'BINInq' => [
                        'Group'    => 'A',
                        'CardType' => 'A',
                    ],
                ],
            ],
            'expected'     => [
                'Customer'    => [
                    'IPAddress'    => '1.1.111.111',
                    'EmailAddress' => 'Cem@cem.com',
                ],
                'Mode'        => 'TEST',
                'Order'       => [
                    'OrderID'     => 'SISTD5A61F1682E745B28871872383ABBEB1',
                    'GroupID'     => '',
                    'Description' => '',
                ],
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'HashData'   => '',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Transaction' => [
                    'Type'   => 'bininq',
                    'Amount' => '1',
                    'BINInq' => [
                        'Group'    => 'A',
                        'CardType' => 'A',
                    ],
                ],
                'Version'     => 'v0.00',
            ],
        ];

        yield 'with_account_data_bin_inquiry' => [
            'request_data' => [
                'Customer'    => [
                    'IPAddress'    => '1.1.111.111',
                    'EmailAddress' => 'Cem@cem.com',
                ],
                'Mode'        => 'TEST',
                'Order'       => [
                    'OrderID'     => 'SISTD5A61F1682E745B28871872383ABBEB1',
                    'GroupID'     => '',
                    'Description' => '',
                ],
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT2',
                    'UserID'     => 'PROVAUT2',
                    'ID'         => '306912982',
                    'MerchantID' => '70006792',
                ],
                'Transaction' => [
                    'Type'   => 'bininq',
                    'Amount' => '1',
                    'BINInq' => [
                        'Group'    => 'A',
                        'CardType' => 'A',
                    ],
                ],
                'Version'     => 'v0.00',
            ],
            'expected'     => [
                'Customer'    => [
                    'IPAddress'    => '1.1.111.111',
                    'EmailAddress' => 'Cem@cem.com',
                ],
                'Mode'        => 'TEST',
                'Order'       => [
                    'OrderID'     => 'SISTD5A61F1682E745B28871872383ABBEB1',
                    'GroupID'     => '',
                    'Description' => '',
                ],
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT2',
                    'UserID'     => 'PROVAUT2',
                    'HashData'   => 'ljflsjflds',
                    'ID'         => '306912982',
                    'MerchantID' => '70006792',
                ],
                'Transaction' => [
                    'Type'   => 'bininq',
                    'Amount' => '1',
                    'BINInq' => [
                        'Group'    => 'A',
                        'CardType' => 'A',
                    ],
                ],
                'Version'     => 'v0.00',
            ],
        ];
    }

    public static function threeDFormDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'ip'          => '156.155.154.153',
        ];

        return [
            'without_card' => [
                'order'        => $order,
                'gatewayUrl'   => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine',
                    'method'  => 'POST',
                    'inputs'  => [
                        'secure3dsecuritylevel' => '3D',
                        'mode'                  => 'TEST',
                        'apiversion'            => '512',
                        'terminalprovuserid'    => 'PROVAUT',
                        'terminaluserid'        => 'PROVAUT',
                        'terminalmerchantid'    => '7000679',
                        'terminalid'            => '30691298',
                        'txntype'               => 'sales',
                        'txnamount'             => '10025',
                        'txncurrencycode'       => '949',
                        'txninstallmentcount'   => '',
                        'orderid'               => 'order222',
                        'successurl'            => 'https://domain.com/success',
                        'errorurl'              => 'https://domain.com/fail_url',
                        'customeripaddress'     => '156.155.154.153',
                        'secure3dhash'          => '372D6CB20B2B699D0A6667DFF46E3AA8CF3F9D8C2BB69A7C411895151FFCFAAB5277CCFE3B3A06035FEEFBFBFD40C79DBE51DBF867D0A24B37335A28F0CEFDE2',
                    ],
                ],
            ],
            'with_card'    => [
                'order'        => $order,
                'gatewayUrl'   => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => true,
                'expected'     => [
                    'gateway' => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine',
                    'method'  => 'POST',
                    'inputs'  => [
                        'secure3dsecuritylevel' => '3D',
                        'mode'                  => 'TEST',
                        'apiversion'            => '512',
                        'terminalprovuserid'    => 'PROVAUT',
                        'terminaluserid'        => 'PROVAUT',
                        'terminalmerchantid'    => '7000679',
                        'terminalid'            => '30691298',
                        'txntype'               => 'sales',
                        'txnamount'             => '10025',
                        'txncurrencycode'       => '949',
                        'txninstallmentcount'   => '',
                        'orderid'               => 'order222',
                        'successurl'            => 'https://domain.com/success',
                        'errorurl'              => 'https://domain.com/fail_url',
                        'customeripaddress'     => '156.155.154.153',
                        'cardnumber'            => '5555444433332222',
                        'cardexpiredatemonth'   => '01',
                        'cardexpiredateyear'    => '22',
                        'cardcvv2'              => '123',
                        'secure3dhash'          => '372D6CB20B2B699D0A6667DFF46E3AA8CF3F9D8C2BB69A7C411895151FFCFAAB5277CCFE3B3A06035FEEFBFBFD40C79DBE51DBF867D0A24B37335A28F0CEFDE2',
                    ],
                ],
            ],
        ];
    }

    public static function cancelRequestDataProvider(): array
    {
        $order = [
            'id'          => '2020110828BC',
            'ip'          => '127.0.0.1',
            'ref_ret_num' => '831803579226',
        ];

        return [
            [
                'order'    => $order,
                'expected' => [
                    'Mode'        => 'TEST',
                    'Version'     => '512',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVRFN',
                        'UserID'     => 'PROVRFN',
                        'HashData'   => '35E8410A78E24949D78F5E025B5E05AF470B01385A2ECBFEE6C5B3CDACFF78011D387ECAFDCE4B8453D80D35C2F344F3DAA6F2EF9143079F64DE88401EC5E4F5',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '127.0.0.1',
                    ],
                    'Order'       => [
                        'OrderID' => '2020110828BC',
                    ],
                    'Transaction' => [
                        'Type'                  => 'void',
                        'InstallmentCnt'        => '',
                        'Amount'                => 100,
                        'CurrencyCode'          => '949',
                        'CardholderPresentCode' => '0',
                        'MotoInd'               => 'N',
                        'OriginalRetrefNum'     => '831803579226',
                    ],
                ],
            ],
        ];
    }

    public static function nonSecurePaymentRequestDataDataProvider(): array
    {
        $card = CreditCardFactory::create(
            '5555444433332222',
            '22',
            '01',
            '123',
            'ahmet',
        );

        return [
            'basic'     => [
                'order'    => [
                    'id'          => 'order222',
                    'amount'      => 100.25,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'installment' => 0,
                    'ip'          => '127.0.0.1',
                ],
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'card'     => $card,
                'expected' => [
                    'Mode'        => 'TEST',
                    'Version'     => '512',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'HashData'   => '2005F771B622399C0EC7B8BBBE9B5F7989B9587175239F0695C1E5D3BFAA0CF6D747A9CEE64D78B7081CB5193541AD9D129B929653E2B68BCAE6939E281D752E',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '127.0.0.1',
                    ],
                    'Card'        => [
                        'Number'     => '5555444433332222',
                        'ExpireDate' => '0122',
                        'CVV2'       => '123',
                    ],
                    'Order'       => [
                        'OrderID' => 'order222',
                    ],
                    'Transaction' => [
                        'Type'                  => 'sales',
                        'InstallmentCnt'        => '',
                        'Amount'                => 10025,
                        'CurrencyCode'          => '949',
                        'CardholderPresentCode' => '0',
                        'MotoInd'               => 'N',
                    ],
                ],
            ],
            'recurring' => [
                'order'    => [
                    'id'        => 'order222',
                    'amount'    => 100.25,
                    'currency'  => PosInterface::CURRENCY_TRY,
                    'ip'        => '127.0.0.1',
                    'recurring' => [
                        'frequency'     => 3,
                        'frequencyType' => 'MONTH',
                        'installment'   => 4,
                        'startDate'     => new \DateTimeImmutable('2024-09-09 00:00:00'),
                    ],
                ],
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'card'     => $card,
                'expected' => [
                    'Mode'        => 'TEST',
                    'Version'     => '512',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'HashData'   => '2005F771B622399C0EC7B8BBBE9B5F7989B9587175239F0695C1E5D3BFAA0CF6D747A9CEE64D78B7081CB5193541AD9D129B929653E2B68BCAE6939E281D752E',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '127.0.0.1',
                    ],
                    'Card'        => [
                        'Number'     => '5555444433332222',
                        'ExpireDate' => '0122',
                        'CVV2'       => '123',
                    ],
                    'Order'       => [
                        'OrderID' => 'order222',
                    ],
                    'Transaction' => [
                        'Type'                  => 'sales',
                        'InstallmentCnt'        => '',
                        'Amount'                => 10025,
                        'CurrencyCode'          => '949',
                        'CardholderPresentCode' => '0',
                        'MotoInd'               => 'N',
                    ],
                    'Recurring'   => [
                        'TotalPaymentNum'   => '4',
                        'FrequencyType'     => 'M',
                        'FrequencyInterval' => '3',
                        'Type'              => 'R',
                        'StartDate'         => '20240909',
                    ],
                ],
            ],
        ];
    }

    public static function nonSecurePaymentPostRequestDataDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'amount'      => 100.25,
            'currency'    => PosInterface::CURRENCY_TRY,
            'ip'          => '127.0.0.1',
            'ref_ret_num' => '831803579226',
        ];

        return [
            [
                'order'    => $order,
                'expected' => [
                    'Mode'        => 'TEST',
                    'Version'     => '512',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'HashData'   => '',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '127.0.0.1',
                    ],
                    'Order'       => [
                        'OrderID' => 'order222',
                    ],
                    'Transaction' => [
                        'Type'              => 'postauth',
                        'Amount'            => 10025,
                        'CurrencyCode'      => '949',
                        'OriginalRetrefNum' => '831803579226',
                    ],
                ],
            ],
        ];
    }

    public static function statusRequestDataDataProvider(): array
    {
        $order = [
            'id' => '2020110828BC',
            'ip' => '127.15.15.1',
        ];

        return [
            [
                'order'    => $order,
                'expected' => [
                    'Mode'        => 'TEST',
                    'Version'     => '512',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'HashData'   => '35E8410A78E24949D78F5E025B5E05AF470B01385A2ECBFEE6C5B3CDACFF78011D387ECAFDCE4B8453D80D35C2F344F3DAA6F2EF9143079F64DE88401EC5E4F5',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '127.15.15.1',
                    ],
                    'Order'       => [
                        'OrderID' => '2020110828BC',
                    ],
                    'Transaction' => [
                        'Type'                  => 'orderinq',
                        'InstallmentCnt'        => '',
                        'Amount'                => 100,
                        'CurrencyCode'          => '949',
                        'CardholderPresentCode' => '0',
                        'MotoInd'               => 'N',
                    ],
                ],
            ],
        ];
    }

    public static function refundOrderDataProvider(): \Generator
    {
        $order = [
            'id'          => '2020110828BC',
            'ip'          => '127.15.15.1',
            'currency'    => PosInterface::CURRENCY_TRY,
            'amount'      => 123.1,
            'ref_ret_num' => '831803579226',
            'installment' => 0,
        ];

        yield [
            'order'        => $order,
            'tx_type'      => PosInterface::TX_TYPE_REFUND,
            'expectedData' => [
                'Mode'        => 'TEST',
                'Version'     => '512',
                'Terminal'    => [
                    'ProvUserID' => 'PROVRFN',
                    'UserID'     => 'PROVRFN',
                    'HashData'   => 'CF49751B3B793B9E1946A08815451989D0231D68A5B495C6EABA9C400442F2E6B7DF97446CE2D3562780767E634A6ECBAA1DF69F6DF7F447884A71BDE38D12AA',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Customer'    => [
                    'IPAddress' => '127.15.15.1',
                ],
                'Order'       => [
                    'OrderID' => '2020110828BC',
                ],
                'Transaction' => [
                    'Type'                  => 'refund',
                    'InstallmentCnt'        => '',
                    'Amount'                => 12310,
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                    'OriginalRetrefNum'     => '831803579226',
                ],
            ],
        ];

        yield [
            'order'        => $order,
            'tx_type'      => PosInterface::TX_TYPE_REFUND_PARTIAL,
            'expectedData' => [
                'Mode'        => 'TEST',
                'Version'     => '512',
                'Terminal'    => [
                    'ProvUserID' => 'PROVRFN',
                    'UserID'     => 'PROVRFN',
                    'HashData'   => 'CF49751B3B793B9E1946A08815451989D0231D68A5B495C6EABA9C400442F2E6B7DF97446CE2D3562780767E634A6ECBAA1DF69F6DF7F447884A71BDE38D12AA',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Customer'    => [
                    'IPAddress' => '127.15.15.1',
                ],
                'Order'       => [
                    'OrderID' => '2020110828BC',
                ],
                'Transaction' => [
                    'Type'                  => 'refund',
                    'InstallmentCnt'        => '',
                    'Amount'                => 12310,
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                    'OriginalRetrefNum'     => '831803579226',
                ],
            ],
        ];
    }

    public static function orderHistoryRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'          => 'order222',
                    'ip'          => '156.155.154.153',
                    'installment' => 0,
                ],
                'expected' => [
                    'Mode'        => 'TEST',
                    'Version'     => '512',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'HashData'   => '817BA6A5013BD1E75E1C2FE82AA0F2EFEF89033C31563575701BD05F3C20ADC5DD2AF65D9EF8CF81784E9DA787603E0C1321C6909BE920504BEB3A85992440F5',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '156.155.154.153',
                    ],
                    'Order'       => [
                        'OrderID' => 'order222',
                    ],
                    'Transaction' => [
                        'Type'                  => 'orderhistoryinq',
                        'InstallmentCnt'        => '',
                        'Amount'                => 100,
                        'CurrencyCode'          => '949',
                        'CardholderPresentCode' => '0',
                        'MotoInd'               => 'N',
                    ],
                ],
            ],
        ];
    }

    public static function historyRequestDataProvider(): array
    {
        return [
            [
                'data'     => [
                    'start_date' => new \DateTime('2022-05-18 00:00:00'),
                    'end_date'   => new \DateTime('2022-05-18 23:59:59'),
                    'ip'         => '127.0.0.1',
                ],
                'expected' => [
                    'Customer' => [
                        'IPAddress' => '127.0.0.1',
                    ],

                    'Mode'  => 'TEST',
                    'Order' => [
                        'OrderID'     => null,
                        'GroupID'     => null,
                        'Description' => null,
                        'StartDate'   => '18/05/2022 00:00',
                        'EndDate'     => '18/05/2022 23:59',
                        'ListPageNum' => 1,
                    ],

                    'Terminal' => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'HashData'   => '9B53A55199EBAD2F486089FD7310C4BC0C61A99FC37EF61F6BBAE67FA17E47641540B203E83C9F2E64DB64B971FE6FF604274316F6D010426D6AA91BE1D924E6',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],

                    'Transaction' => [
                        'Type'                  => 'orderlistinq',
                        'Amount'                => 100,
                        'CurrencyCode'          => '949',
                        'CardholderPresentCode' => '0',
                        'MotoInd'               => 'N',
                    ],
                    'Version'     => '512',
                ],
            ],
        ];
    }

    public static function create3DPaymentRequestDataDataProvider(): \Generator
    {
        $account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            PosInterface::MODEL_3D_SECURE,
            '12345678',
            'PROVRFN',
            '123qweASD/'
        );

        $order = [
            'id'          => '2020110828BC',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'ip'          => '156.155.154.153',
        ];

        $responseData = [
            'orderid'           => '2020110828BC',
            'md'                => '1',
            'xid'               => '100000005xid',
            'eci'               => '100000005eci',
            'cavv'              => 'cavv',
            'txncurrencycode'   => '949',
            'txnamount'         => '100.25',
            'txntype'           => 'sales',
            'customeripaddress' => '127.0.0.1',
        ];

        yield [
            'account'      => $account,
            'order'        => $order,
            'responseData' => $responseData,
            'expected'     => [
                'Mode'        => 'TEST',
                'Version'     => '512',
                'Terminal'    => [
                    'ProvUserID' => $account->getUsername(),
                    'UserID'     => $account->getUsername(),
                    'HashData'   => 'C7806BCDC5874CD227C4B7278302077F6B3E463A9C74497F263E8AF08844DEF364F4D7A089084A705D4210B9B36841E0919B72F804729F466BF00472C53AFD8B',
                    'ID'         => $account->getTerminalId(),
                    'MerchantID' => $account->getClientId(),
                ],
                'Customer'    => [
                    'IPAddress' => '127.0.0.1',
                ],
                'Order'       => [
                    'OrderID' => '2020110828BC',
                ],
                'Transaction' => [
                    'Type'                  => 'sales',
                    'InstallmentCnt'        => '',
                    'Amount'                => '100.25',
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '13',
                    'MotoInd'               => 'N',
                    'Secure3D'              => [
                        'AuthenticationCode' => 'cavv',
                        'SecurityLevel'      => '100000005eci',
                        'TxnID'              => '100000005xid',
                        'Md'                 => '1',
                    ],
                ],
            ],
        ];

        $order['recurring']   = [
            'frequency'     => 2,
            'frequencyType' => 'MONTH',
            'installment'   => 3,
            'startDate'     => new \DateTimeImmutable('2023-10-15'),
        ];
        $order['installment'] = 0;

        yield 'recurring_order' => [
            'account'      => $account,
            'order'        => $order,
            'responseData' => $responseData,
            'expected'     => [
                'Mode'        => 'TEST',
                'Version'     => '512',
                'Terminal'    => [
                    'ProvUserID' => $account->getUsername(),
                    'UserID'     => $account->getUsername(),
                    'HashData'   => 'C7806BCDC5874CD227C4B7278302077F6B3E463A9C74497F263E8AF08844DEF364F4D7A089084A705D4210B9B36841E0919B72F804729F466BF00472C53AFD8B',
                    'ID'         => $account->getTerminalId(),
                    'MerchantID' => $account->getClientId(),
                ],
                'Customer'    => [
                    'IPAddress' => '127.0.0.1',
                ],
                'Order'       => [
                    'OrderID' => '2020110828BC',
                ],
                'Transaction' => [
                    'Type'                  => 'sales',
                    'InstallmentCnt'        => '',
                    'Amount'                => '100.25',
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '13',
                    'MotoInd'               => 'N',
                    'Secure3D'              => [
                        'AuthenticationCode' => 'cavv',
                        'SecurityLevel'      => '100000005eci',
                        'TxnID'              => '100000005xid',
                        'Md'                 => '1',
                    ],
                ],
                'Recurring'   => [
                    'TotalPaymentNum'   => '3',
                    'FrequencyType'     => 'M',
                    'FrequencyInterval' => '2',
                    'Type'              => 'R',
                    'StartDate'         => '20231015',
                ],
            ],
        ];
    }
}

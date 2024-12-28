<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\InterPosRequestDataMapper;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\InterPosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class InterPosRequestDataMapperTest extends TestCase
{
    private InterPosAccount $account;

    private CreditCardInterface $card;

    private InterPosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $userCode     = 'InterTestApi';
        $userPass     = '3';
        $shopCode     = '3123';
        $merchantPass = 'gDg1N';

        $this->account = AccountFactory::createInterPosAccount(
            'denizbank',
            $shopCode,
            $userCode,
            $userPass,
            PosInterface::MODEL_3D_SECURE,
            $merchantPass
        );

        $this->dispatcher        = $this->createMock(EventDispatcherInterface::class);
        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new InterPosRequestDataMapper($this->dispatcher, $this->crypt);

        $this->card = CreditCardFactory::create('5555444433332222', '21', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
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
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider createNonSecurePaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, CreditCardInterface $creditCard, array $expected): void
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $creditCard
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
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(array $order, array $responseData, array $expectedData): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData(
            $this->account,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $responseData
        );

        $this->assertSame($expectedData, $actual);
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
                && InterPos::class === $dispatchedEvent->getGatewayClass()
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
    public function testCreateStatusRequestData(array $order, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider refundOrderDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public function testCreateOrderHistoryRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createOrderHistoryRequestData($this->account, []);
    }

    public function testCreateHistoryRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createHistoryRequestData($this->account, []);
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
        yield 'without_account_data' => [
            'request_data' => [
                'abc' => '124',
            ],
            'expected'     => [
                'abc'      => '124',
                'ShopCode' => '3123',
                'UserCode' => 'InterTestApi',
                'UserPass' => '3',
            ],
        ];

        yield 'with_account_data' => [
            'request_data' => [
                'abc'      => '124',
                'ShopCode' => '31231',
                'UserCode' => 'InterTestApi1',
                'UserPass' => '31',
            ],
            'expected'     => [
                'abc'      => '124',
                'ShopCode' => '31231',
                'UserCode' => 'InterTestApi1',
                'UserPass' => '31',
            ],
        ];
    }

    public static function create3DPaymentRequestDataDataProvider(): array
    {
        return [
            [
                'order'        => [
                    'id'     => 'order222',
                    'amount' => '100.25',
                    'lang'   => PosInterface::LANG_TR,
                ],
                'responseData' => [
                    'MD'                      => '1',
                    'PayerTxnId'              => '2',
                    'Eci'                     => '3',
                    'PayerAuthenticationCode' => '4',
                ],
                'expected'     => [
                    'UserCode'                => 'InterTestApi',
                    'UserPass'                => '3',
                    'ShopCode'                => '3123',
                    'TxnType'                 => 'Auth',
                    'SecureType'              => 'NonSecure',
                    'OrderId'                 => 'order222',
                    'PurchAmount'             => '100.25',
                    'Currency'                => '949',
                    'InstallmentCount'        => '',
                    'MD'                      => '1',
                    'PayerTxnId'              => '2',
                    'Eci'                     => '3',
                    'PayerAuthenticationCode' => '4',
                    'MOTO'                    => '0',
                    'Lang'                    => PosInterface::LANG_TR,
                ],
            ],
        ];
    }

    public static function createCancelRequestDataDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'   => '2020110828BC',
                    'lang' => PosInterface::LANG_EN,
                ],
                'expected' => [
                    'UserCode'   => 'InterTestApi',
                    'UserPass'   => '3',
                    'ShopCode'   => '3123',
                    'OrderId'    => null,
                    'orgOrderId' => '2020110828BC',
                    'TxnType'    => 'Void',
                    'SecureType' => 'NonSecure',
                    'Lang'       => PosInterface::LANG_EN,
                ],
            ],
        ];
    }


    public static function createNonSecurePaymentRequestDataDataProvider(): array
    {
        $card = CreditCardFactory::create(
            '5555444433332222',
            '21',
            '12',
            '122',
            'ahmet',
            CreditCardInterface::CARD_TYPE_VISA
        );

        return [
            [
                'order'    => [
                    'id'          => 'order222',
                    'amount'      => '100.25',
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'lang'        => PosInterface::LANG_TR,
                ],
                'card'     => $card,
                'expected' => [
                    'UserCode'         => 'InterTestApi',
                    'UserPass'         => '3',
                    'ShopCode'         => '3123',
                    'TxnType'          => 'Auth',
                    'SecureType'       => 'NonSecure',
                    'OrderId'          => 'order222',
                    'PurchAmount'      => '100.25',
                    'Currency'         => '949',
                    'InstallmentCount' => '',
                    'MOTO'             => '0',
                    'Lang'             => PosInterface::LANG_TR,
                    'CardType'         => '0',
                    'Pan'              => $card->getNumber(),
                    'Expiry'           => '1221',
                    'Cvv2'             => $card->getCvv(),
                ],
            ],
        ];
    }

    public static function nonSecurePaymentPostRequestDataDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'     => 'order222',
                    'amount' => 10.0,
                ],
                'expected' => [
                    'UserCode'    => 'InterTestApi',
                    'UserPass'    => '3',
                    'ShopCode'    => '3123',
                    'TxnType'     => 'PostAuth',
                    'SecureType'  => 'NonSecure',
                    'OrderId'     => null,
                    'orgOrderId'  => 'order222',
                    'PurchAmount' => '10',
                    'Currency'    => '949',
                    'MOTO'        => '0',
                ],
            ],
        ];
    }

    public static function createStatusRequestDataDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'   => 'order222',
                    'lang' => PosInterface::LANG_TR,
                ],
                'expected' => [
                    'UserCode'   => 'InterTestApi',
                    'UserPass'   => '3',
                    'ShopCode'   => '3123',
                    'OrderId'    => null,
                    'orgOrderId' => 'order222',
                    'TxnType'    => 'StatusHistory',
                    'SecureType' => 'NonSecure',
                    'Lang'       => PosInterface::LANG_TR,
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
                'gatewayUrl'   => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                    'method'  => 'POST',
                    'inputs'  => [
                        'ShopCode'         => '3123',
                        'TxnType'          => 'Auth',
                        'SecureType'       => '3DModel',
                        'PurchAmount'      => '100.25',
                        'OrderId'          => 'order222',
                        'OkUrl'            => 'https://domain.com/success',
                        'FailUrl'          => 'https://domain.com/fail_url',
                        'Rnd'              => 'rand-12',
                        'Lang'             => 'tr',
                        'Currency'         => '949',
                        'InstallmentCount' => '',
                        'Hash'             => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
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
                        'ShopCode'         => '3123',
                        'TxnType'          => 'Auth',
                        'SecureType'       => '3DModel',
                        'PurchAmount'      => '100.25',
                        'OrderId'          => 'order222',
                        'OkUrl'            => 'https://domain.com/success',
                        'FailUrl'          => 'https://domain.com/fail_url',
                        'Rnd'              => 'rand-12',
                        'Lang'             => 'tr',
                        'Currency'         => '949',
                        'InstallmentCount' => '',
                        'CardType'         => '0',
                        'Pan'              => '5555444433332222',
                        'Expiry'           => '1221',
                        'Cvv2'             => '122',
                        'Hash'             => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
                    ],
                ],
            ],
            '3d_host'      => [
                'order'        => $order,
                'gatewayUrl'   => 'https://test.inter-vpos.com.tr/mpi/3DHost.aspx',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://test.inter-vpos.com.tr/mpi/3DHost.aspx',
                    'method'  => 'POST',
                    'inputs'  => [
                        'ShopCode'         => '3123',
                        'TxnType'          => 'Auth',
                        'SecureType'       => '3DHost',
                        'PurchAmount'      => '100.25',
                        'OrderId'          => 'order222',
                        'OkUrl'            => 'https://domain.com/success',
                        'FailUrl'          => 'https://domain.com/fail_url',
                        'Rnd'              => 'rand-12',
                        'Lang'             => 'tr',
                        'Currency'         => '949',
                        'InstallmentCount' => '',
                        'Hash'             => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
                    ],
                ],
            ],
        ];
    }

    public static function refundOrderDataProvider(): \Generator
    {
        $order = [
            'id'     => '2020110828BC',
            'amount' => 123.1,
        ];

        yield [
            'order'        => $order,
            'tx_type'      => PosInterface::TX_TYPE_REFUND,
            'expectedData' => [
                'Lang'        => 'tr',
                'MOTO'        => '0',
                'OrderId'     => null,
                'PurchAmount' => '123.1',
                'SecureType'  => 'NonSecure',
                'ShopCode'    => '3123',
                'TxnType'     => 'Refund',
                'UserCode'    => 'InterTestApi',
                'UserPass'    => '3',
                'orgOrderId'  => '2020110828BC',
            ],
        ];

        yield [
            'order'        => $order,
            'tx_type'      => PosInterface::TX_TYPE_REFUND_PARTIAL,
            'expectedData' => [
                'Lang'        => 'tr',
                'MOTO'        => '0',
                'OrderId'     => null,
                'PurchAmount' => '123.1',
                'SecureType'  => 'NonSecure',
                'ShopCode'    => '3123',
                'TxnType'     => 'Refund',
                'UserCode'    => 'InterTestApi',
                'UserPass'    => '3',
                'orgOrderId'  => '2020110828BC',
            ],
        ];
    }
}

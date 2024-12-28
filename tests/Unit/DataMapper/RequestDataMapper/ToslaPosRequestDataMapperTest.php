<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\ToslaPosRequestDataMapper;
use Mews\Pos\Entity\Account\ToslaPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\ToslaPosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class ToslaPosRequestDataMapperTest extends TestCase
{
    private ToslaPosAccount $account;

    private CreditCardInterface $card;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    private ToslaPosRequestDataMapper $requestDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createToslaPosAccount(
            'tosla',
            '1000000494',
            'POS_ENT_Test_001',
            'POS_ENT_Test_001!*!*',
        );

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new ToslaPosRequestDataMapper($this->dispatcher, $this->crypt);
        $this->card              = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    /**
     * @testWith ["pay", "1"]
     * ["pre", "2"]
     */
    public function testMapTxType(string $txType, string $expected): void
    {
        $actual = $this->requestDataMapper->mapTxType($txType);
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
     * @dataProvider nonSecurePaymentPostRequestDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expected): void
    {
        $requestDataWithoutHash = $expected;
        unset($requestDataWithoutHash['hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $requestDataWithoutHash)
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider paymentRegisterRequestDataProvider
     */
    public function testCreate3DEnrollmentCheckRequestData(array $order, string $paymentModel, string $txType, array $expected): void
    {
        $requestDataWithoutHash = $expected;
        unset($requestDataWithoutHash['hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $requestDataWithoutHash)
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $order);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, string $txType, array $expected): void
    {
        $requestDataWithoutHash = $expected;
        unset($requestDataWithoutHash['hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $requestDataWithoutHash)
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, $txType, $this->card);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider cancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected): void
    {
        $requestDataWithoutHash = $expected;
        unset($requestDataWithoutHash['hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $requestDataWithoutHash)
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider orderHistoryRequestDataProvider
     */
    public function testCreateOrderHistoryRequestData(array $order, array $expected): void
    {
        $requestDataWithoutHash = $expected;
        unset($requestDataWithoutHash['hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $requestDataWithoutHash)
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);

        $this->assertSame($expected, $actual);
    }


    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(array $order, string $txType, string $paymentModel, bool $withCard, string $gatewayURL, array $expected): void
    {
        $card = $withCard ? $this->card : null;

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
            $card
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider statusRequestDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected): void
    {
        $requestDataWithoutHash = $expected;
        unset($requestDataWithoutHash['hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $requestDataWithoutHash)
            ->willReturn($expected['hash']);

        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $this->assertSame($expected, $actualData);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expected): void
    {
        $requestDataWithoutHash = $expected;
        unset($requestDataWithoutHash['hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $requestDataWithoutHash)
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        ksort($actual);
        ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public function testCreate3DPaymentRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->create3DPaymentRequestData(
            $this->account,
            [],
            PosInterface::TX_TYPE_PAY_AUTH,
            []
        );
    }

    public function testCreateHistoryRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createHistoryRequestData($this->account);
    }

    /**
     * @dataProvider createCustomQueryRequestDataDataProvider
     */
    public function testCreateCustomQueryRequestData(array $requestData, array $expectedData): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expectedData['rnd']);
        if (!isset($requestData['hash'])) {
            $this->crypt->expects(self::once())
                ->method('createHash')
                ->willReturn($expectedData['hash']);
        }

        $actual = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);
        $this->assertSame(14, \strlen($actual['timeSpan']));
        unset($actual['timeSpan'], $expectedData['timeSpan']);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data_installment_option_inquiry' => [
            'request_data' => [
                'bin' => 415956,
            ],
            'expected'     => [
                'apiUser'  => 'POS_ENT_Test_001',
                'bin'      => 415956,
                'clientId' => '1000000494',
                'hash'     => '12fsdfdsfsfs',
                'rnd'      => 'rndsfldfls',
                'timeSpan' => '20241103144302',
            ],
        ];

        yield 'with_account_data_installment_option_inquiry' => [
            'request_data' => [
                'apiUser'  => 'POS_ENT_Test_001xxx',
                'bin'      => 415956,
                'clientId' => '1000000494xx',
                'hash'     => '12fsdfdsfsfsxxx',
                'rnd'      => 'rndsfldfls',
                'timeSpan' => '20241103144302',
            ],
            'expected'     => [
                'apiUser'  => 'POS_ENT_Test_001xxx',
                'bin'      => 415956,
                'clientId' => '1000000494xx',
                'hash'     => '12fsdfdsfsfsxxx',
                'rnd'      => 'rndsfldfls',
                'timeSpan' => '20241103144302',
            ],
        ];
    }

    public static function statusRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'        => 'id-12',
                    'time_span' => '20231209215355',
                ],
                'expected' => [
                    'clientId' => '1000000494',
                    'apiUser'  => 'POS_ENT_Test_001',
                    'orderId'  => 'id-12',
                    'rnd'      => 'rand-212s',
                    'timeSpan' => '20231209215355',
                    'hash'     => 'jcgZOAf/m/E1uwYXmOPrdgqXRuEmVuG3Q14yri0okhULvRDbAicyAU8hflUP634yRRSZkOnIOeLZNqXfLhzz7g==',
                ],
            ],
        ];
    }

    public static function cancelRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'        => 'id-12',
                    'time_span' => '20231209215355',
                ],
                'expected' => [
                    'clientId' => '1000000494',
                    'apiUser'  => 'POS_ENT_Test_001',
                    'orderId'  => 'id-12',
                    'rnd'      => 'rand-212s',
                    'timeSpan' => '20231209215355',
                    'hash'     => 'jcgZOAf/m/E1uwYXmOPrdgqXRuEmVuG3Q14yri0okhULvRDbAicyAU8hflUP634yRRSZkOnIOeLZNqXfLhzz7g==',
                ],
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'        => 'id-12',
                    'amount'    => 1.02,
                    'time_span' => '20231209215355',
                ],
                'tx_type'  => PosInterface::TX_TYPE_REFUND,
                'expected' => [
                    'clientId' => '1000000494',
                    'apiUser'  => 'POS_ENT_Test_001',
                    'orderId'  => 'id-12',
                    'rnd'      => 'rand-212s',
                    'timeSpan' => '20231209215355',
                    'hash'     => 'jcgZOAf/m/E1uwYXmOPrdgqXRuEmVuG3Q14yri0okhULvRDbAicyAU8hflUP634yRRSZkOnIOeLZNqXfLhzz7g==',
                    'amount'   => 102,
                ],
            ],
        ];
    }

    public static function paymentRegisterRequestDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'amount'      => 100.25,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'time_span'   => '20231209214708',
        ];

        return [
            [
                'order'        => $order,
                'paymentModel' => PosInterface::MODEL_3D_PAY,
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'expected'     => [
                    'clientId'         => '1000000494',
                    'apiUser'          => 'POS_ENT_Test_001',
                    'callbackUrl'      => 'https://domain.com/success',
                    'orderId'          => 'order222',
                    'amount'           => 10025,
                    'currency'         => 949,
                    'installmentCount' => 0,
                    'rnd'              => 'rand',
                    'timeSpan'         => '20231209214708',
                    'hash'             => '+XGO1qv+6W7nXZwSsYMaRrWXhi+99jffLvExGsFDodYyNadOG7OQKsygzly5ESDoNIS19oD2U+hSkVeT6UTAFA==',
                ],
            ],
        ];
    }

    public static function nonSecurePaymentRequestDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'amount'      => 100.25,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'time_span'   => '20231209214708',
        ];

        return [
            [
                'order'    => $order,
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => [
                    'clientId'         => '1000000494',
                    'apiUser'          => 'POS_ENT_Test_001',
                    'orderId'          => 'order222',
                    'amount'           => 10025,
                    'currency'         => 949,
                    'installmentCount' => 0,
                    'rnd'              => 'rand',
                    'timeSpan'         => '20231209214708',
                    'cardHolderName'   => 'ahmet',
                    'cardNo'           => '5555444433332222',
                    'expireDate'       => '0122',
                    'cvv'              => '123',
                    'hash'             => '+XGO1qv+6W7nXZwSsYMaRrWXhi+99jffLvExGsFDodYyNadOG7OQKsygzly5ESDoNIS19oD2U+hSkVeT6UTAFA==',
                ],
            ],
        ];
    }

    public static function nonSecurePaymentPostRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'        => '2020110828BC',
                    'amount'    => 1.10,
                    'time_span' => '20231209213944',
                ],
                'expected' => [
                    'clientId' => '1000000494',
                    'apiUser'  => 'POS_ENT_Test_001',
                    'orderId'  => '2020110828BC',
                    'amount'   => 110,
                    'rnd'      => 'raranra',
                    'timeSpan' => '20231209213944',
                    'hash'     => 'bLp8WNYiaKrnW+EfwHuUZ1ovao0laSeuU/DqUMhjI40QNWdcHJWRKpkoE+eb1U07GGDgsIKKvx9nh84s6K1+pQ==',
                ],
            ],
        ];
    }

    public static function orderHistoryRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'               => '2020110828BC',
                    'time_span'        => '20231209215355',
                    'transaction_date' => new \DateTime('2023-12-09 00:00:00'),
                ],
                'expected' => [
                    'clientId'        => '1000000494',
                    'apiUser'         => 'POS_ENT_Test_001',
                    'orderId'         => '2020110828BC',
                    'transactionDate' => '20231209',
                    'page'            => 1,
                    'pageSize'        => 10,
                    'rnd'             => 'rand-123',
                    'timeSpan'        => '20231209215355',
                    'hash'            => 'csrJh00U/nYGim8jPp9uddRdRvEWZf8pYu9Elss5RtA9JQt6DRkXxrTTY4iYQt5iABp5bj+/RYhlnTflyD9eBw==',
                ],
            ],
            [
                'order'    => [
                    'id'               => '2020110828BC',
                    'time_span'        => '20231209215355',
                    'page'             => 2,
                    'page_size'        => 5,
                    'transaction_date' => new \DateTime('2023-12-09 00:00:00'),
                ],
                'expected' => [
                    'clientId'        => '1000000494',
                    'apiUser'         => 'POS_ENT_Test_001',
                    'orderId'         => '2020110828BC',
                    'transactionDate' => '20231209',
                    'page'            => 2,
                    'pageSize'        => 5,
                    'rnd'             => 'rand-123',
                    'timeSpan'        => '20231209215355',
                    'hash'            => 'csrJh00U/nYGim8jPp9uddRdRvEWZf8pYu9Elss5RtA9JQt6DRkXxrTTY4iYQt5iABp5bj+/RYhlnTflyD9eBw==',
                ],
            ],
        ];
    }

    public static function threeDFormDataProvider(): array
    {
        return [
            '3d_host_form_data' => [
                'order'         => [],
                'tx_type'       => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model' => PosInterface::MODEL_3D_HOST,
                'is_with_card'  => false,
                'gateway'       => 'https://ent.akodepos.com/api/Payment/threeDSecure/A2A6E942BD2AE4A68BC42FE99D1BC917D67AFF54AB2BA44EBA675843744187708',
                'expected'      => [
                    'gateway' => 'https://ent.akodepos.com/api/Payment/threeDSecure/A2A6E942BD2AE4A68BC42FE99D1BC917D67AFF54AB2BA44EBA675843744187708',
                    'method'  => 'GET',
                    'inputs'  => [],
                ],
            ],
            '3d_pay_form_data'  => [
                'order'         => [
                    'ThreeDSessionId' => 'P6D383818909442128AB50AB1EC7A4B83080874341688447DA74B90150C8857F2',
                    'TransactionId'   => '2000000000032631',
                    'Code'            => 0,
                    'Message'         => 'Başarılı',
                ],
                'tx_type'       => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model' => PosInterface::MODEL_3D_PAY,
                'is_with_card'  => true,
                'gateway'       => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
                'expected' => [
                    'gateway' => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
                    'method'  => 'POST',
                    'inputs'  => [
                        'ThreeDSessionId' => 'P6D383818909442128AB50AB1EC7A4B83080874341688447DA74B90150C8857F2',
                        'CardHolderName'  => 'ahmet',
                        'CardNo'          => '5555444433332222',
                        'ExpireDate'      => '01/22',
                        'Cvv'             => '123',
                    ],
                ],
            ],
        ];
    }
}

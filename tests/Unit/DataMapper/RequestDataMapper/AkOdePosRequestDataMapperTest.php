<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\AkOdePosRequestDataMapper;
use Mews\Pos\Entity\Account\AkOdePosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AkOdePosRequestDataMapper
 */
class AkOdePosRequestDataMapperTest extends TestCase
{
    private AkOdePosAccount $account;

    private CreditCardInterface $card;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    private AkOdePosRequestDataMapper $requestDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../../config/pos_test.php';

        $this->account = AccountFactory::createAkOdePosAccount(
            'akode',
            '1000000494',
            'POS_ENT_Test_001',
            'POS_ENT_Test_001!*!*',
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos        = PosFactory::createPosGateway($this->account, $config, $dispatcher);

        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new AkOdePosRequestDataMapper($dispatcher, $this->crypt);
        $this->card              = CreditCardFactory::create($pos, '5555444433332222', '22', '01', '123', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
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
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider paymentRegisterRequestDataProvider
     */
    public function testCreate3DEnrollmentCheckRequestData(array $order, string $paymentModel, string $txType, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('create3DHash')
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $order);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, string $txType, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, $txType, $this->card);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider cancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected)
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider orderHistoryRequestDataProvider
     */
    public function testCreateOrderHistoryRequestData(array $order, array $expected)
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);

        $this->assertEquals($expected, $actual);
    }


    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(array $order, string $txType, string $paymentModel, bool $withCard, string $gatewayURL, array $expected)
    {
        $card = $withCard ? $this->card : null;

        $this->crypt->expects(self::never())
            ->method('create3DHash');

        $this->crypt->expects(self::never())
            ->method('generateRandomString');

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
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->willReturn($expected['hash']);

        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $this->assertEquals($expected, $actualData);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, array $expected): void
    {
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['rnd']);
        $this->crypt->expects(self::once())
            ->method('createHash')
            ->willReturn($expected['hash']);

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $this->assertEquals($expected, $actual);
    }

    public static function statusRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'       => 'id-12',
                    'timeSpan' => '20231209215355',
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
                    'id'       => 'id-12',
                    'timeSpan' => '20231209215355',
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
                    'id'       => 'id-12',
                    'amount'   => 1.02,
                    'timeSpan' => '20231209215355',
                ],
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
            'timeSpan'    => '20231209214708',
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
            'timeSpan'    => '20231209214708',
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
                    'id'       => '2020110828BC',
                    'amount'   => 1.10,
                    'timeSpan' => '20231209213944',
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
                'order' => [
                    'id'               => '2020110828BC',
                    'timeSpan'         => '20231209215355',
                    'transactionDate' => new \DateTime('2023-12-09 00:00:00'),
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
                'order' => [
                    'id'               => '2020110828BC',
                    'timeSpan'         => '20231209215355',
                    'page'             => 2,
                    'pageSize'         => 5,
                    'transactionDate' => new \DateTime('2023-12-09 00:00:00'),
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
                'is_with_card'  => false,
                'gateway'       => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
                'expected'      => [
                    'gateway' => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
                    'method'  => 'POST',
                    'inputs'  => [
                        'ThreeDSessionId' => 'P6D383818909442128AB50AB1EC7A4B83080874341688447DA74B90150C8857F2',
                    ],
                ],
            ],
        ];
    }
}

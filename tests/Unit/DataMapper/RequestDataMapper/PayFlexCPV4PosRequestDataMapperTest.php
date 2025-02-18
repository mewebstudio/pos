<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCard;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class PayFlexCPV4PosRequestDataMapperTest extends TestCase
{
    public PayFlexAccount $account;

    private PayFlexCPV4PosRequestDataMapper $requestDataMapper;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayFlexAccount(
            'vakifbank-cp',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            PosInterface::MODEL_3D_SECURE
        );

        $this->dispatcher        = $this->createMock(EventDispatcherInterface::class);
        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new PayFlexCPV4PosRequestDataMapper($this->dispatcher, $this->crypt);
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
     * @testWith ["Sale"]
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
        $this->assertSame('1000.00', $method->invokeArgs($this->requestDataMapper, [1000]));
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
     * @dataProvider create3DPaymentStatusRequestDataProvider
     */
    public function testCreate3DPaymentStatusRequestData(AbstractPosAccount $posAccount, array $postData, array $expectedData): void
    {
        $actual = $this->requestDataMapper->create3DPaymentStatusRequestData(
            $posAccount,
            $postData,
        );
        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider registerDataProvider
     */
    public function testCreate3DEnrollmentCheckData(AbstractPosAccount $posAccount, array $order, string $txType, ?CreditCard $creditCard, array $expectedData): void
    {
        $hashCalculationData = $expectedData;
        unset($hashCalculationData['HashedData']);

        $this->crypt->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashCalculationData)
            ->willReturn($expectedData['HashedData']);

        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $posAccount,
            $order,
            $txType,
            PosInterface::MODEL_3D_SECURE,
            $creditCard
        );
        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testCreate3DFormData(array $queryParams, array $expected): void
    {
        $this->dispatcher->expects(self::never())
            ->method('dispatch');

        $actualData = $this->requestDataMapper->create3DFormData(
            null,
            null,
            null,
            null,
            null,
            null,
            $queryParams
        );

        $this->assertSame($expected, $actualData);
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

    public function testCreateStatusRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createStatusRequestData($this->account, []);
    }

    public function testCreateCancelRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createCancelRequestData($this->account, []);
    }

    public function testCreateRefundRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createRefundRequestData($this->account, [], PosInterface::TX_TYPE_REFUND);
    }

    public function testCreateNonSecurePaymentRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $card = $this->createMock(CreditCardInterface::class);
        $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            [],
            PosInterface::TX_TYPE_PAY_AUTH,
            $card
        );
    }

    public function testCreateNonSecurePostAuthPaymentRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData(
            $this->account,
            [],
        );
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
        $actual = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data_bin_inquiry' => [
            'request_data' => [
                'abc' => 'abc',
            ],
            'expected'     => [
                'abc'            => 'abc',
                'HostMerchantId' => '000000000111111',
                'Password'       => '3XTgER89as',
            ],
        ];

        yield 'with_account_data_bin_inquiry' => [
            'request_data' => [
                'abc'            => 'abc',
                'HostMerchantId' => '000000000111111xxx',
                'Password'       => '3XTgER89asxxx',
            ],
            'expected'     => [
                'abc'            => 'abc',
                'HostMerchantId' => '000000000111111xxx',
                'Password'       => '3XTgER89asxxx',
            ],
        ];
    }


    public static function registerDataProvider(): iterable
    {
        $account = AccountFactory::createPayFlexAccount(
            'vakifbank-cp',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            PosInterface::MODEL_3D_SECURE
        );

        $order = [
            'id'          => 'order222',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'ip'          => '127.0.0.1',
        ];

        $card = CreditCardFactory::create('5555444433332222', '2021', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);

        yield 'with_card_1' => [
            'account'  => $account,
            'order'    => $order,
            'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
            'card'     => $card,
            'expected' => [
                'HostMerchantId'       => '000000000111111',
                'MerchantPassword'     => '3XTgER89as',
                'HostTerminalId'       => 'VP999999',
                'TransactionType'      => 'Sale',
                'AmountCode'           => '949',
                'Amount'               => '100.00',
                'OrderID'              => 'order222',
                'IsSecure'             => 'true',
                'AllowNotEnrolledCard' => 'false',
                'SuccessUrl'           => 'https://domain.com/success',
                'FailUrl'              => 'https://domain.com/fail_url',
                'RequestLanguage'      => 'tr-TR',
                'Extract'              => '',
                'CustomItems'          => '',
                'BrandNumber'          => '100',
                'CVV'                  => '122',
                'PAN'                  => '5555444433332222',
                'ExpireMonth'          => '12',
                'ExpireYear'           => '21',
                'CardHoldersName'      => 'ahmet',
                'HashedData'           => 'apZ/1+eWzqCRk9qqACxN0bBZQ8g=',
            ],
        ];

        $order['installment'] = 2;

        yield 'without_card_1_pre_pay_and_with_installment' => [
            'account'  => $account,
            'order'    => $order,
            'txType'   => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            'card'     => null,
            'expected' => [
                'HostMerchantId'       => '000000000111111',
                'MerchantPassword'     => '3XTgER89as',
                'HostTerminalId'       => 'VP999999',
                'TransactionType'      => 'Auth',
                'AmountCode'           => '949',
                'Amount'               => '100.00',
                'OrderID'              => 'order222',
                'IsSecure'             => 'true',
                'AllowNotEnrolledCard' => 'false',
                'SuccessUrl'           => 'https://domain.com/success',
                'FailUrl'              => 'https://domain.com/fail_url',
                'RequestLanguage'      => 'tr-TR',
                'Extract'              => '',
                'CustomItems'          => '',
                'InstallmentCount'     => '2',
                'HashedData'           => 'apZ/1+eWzqCRk9qqACxN0bBZQ8g=',
            ],
        ];
    }

    public static function create3DPaymentStatusRequestDataProvider(): iterable
    {
        $account = AccountFactory::createPayFlexAccount(
            'vakifbank-cp',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            PosInterface::MODEL_3D_SECURE
        );

        $postData = [
            'TransactionId' => '3ee068d5b5a747ada65dafc0016d5887',
            'PaymentToken'  => 'b35a56bf37334872a945afc0016d5887',
        ];

        yield 'with_card_1' => [
            'account'  => $account,
            'post_data'    => $postData,
            'expected' => [
                'HostMerchantId' => '000000000111111',
                'Password'       => '3XTgER89as',
                'TransactionId'  => '3ee068d5b5a747ada65dafc0016d5887',
                'PaymentToken'   => 'b35a56bf37334872a945afc0016d5887',
            ],
        ];
    }

    public static function threeDFormDataProvider(): iterable
    {
        yield 'success_1' => [
            'queryParams' => [
                'CommonPaymentUrl' => 'https://cptest.vakifbank.com.tr/CommonPayment/SecurePayment',
                'PaymentToken'     => 'c5e076e7bf234a339c40afc10166c06d',
                'ErrorCode'        => null,
                'ResponseMessage'  => null,
            ],
            'expected'    => [
                'gateway' => 'https://cptest.vakifbank.com.tr/CommonPayment/SecurePayment',
                'method' => 'GET',
                'inputs' => [
                    'Ptkn' => 'c5e076e7bf234a339c40afc10166c06d',
                ],
            ],
        ];
    }
}

<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\DataMapper\RequestDataMapper;

use Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCard;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper
 */
class PayFlexCPV4PosRequestDataMapperTest extends TestCase
{
    public PayFlexAccount $account;

    private PayFlexCPV4PosRequestDataMapper $requestDataMapper;

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

        $crypt                   = CryptFactory::createGatewayCrypt(PayFlexCPV4Pos::class, new NullLogger());
        $this->requestDataMapper = new PayFlexCPV4PosRequestDataMapper($this->createMock(EventDispatcherInterface::class), $crypt);
    }

    /**
     * @return void
     */
    public function testAmountFormat()
    {
        $this->assertEquals('1000.00', $this->requestDataMapper->amountFormat(1000));
    }

    /**
     * @return void
     */
    public function testMapRecurringFrequency()
    {
        $this->assertEquals('Month', $this->requestDataMapper->mapRecurringFrequency('MONTH'));
        $this->assertEquals('Month', $this->requestDataMapper->mapRecurringFrequency('Month'));
    }

    /**
     * @return void
     */
    public function testMapCurrency()
    {
        $this->assertEquals('949', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertEquals('978', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_EUR));
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
    public function testMapInstallment($installment, $expected)
    {
        $actual = $this->requestDataMapper->mapInstallment($installment);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider registerDataProvider
     */
    public function testCreate3DEnrollmentCheckData(AbstractPosAccount $account, array $order, string $txType, ?CreditCard $card, array $expectedData): void
    {
        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $account,
            $order,
            $txType,
            PosInterface::MODEL_3D_SECURE,
            $card
        );
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testCreate3DFormData(array $queryParams, array $expected): void
    {
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

    public static function registerDataProvider(): iterable
    {
        $config = require __DIR__.'/../../../config/pos_test.php';

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
            'rand'        => microtime(true),
            'ip'          => '127.0.0.1',
        ];

        $pos = PosFactory::createPosGateway($account, $config, new EventDispatcher());
        $pos->setTestMode(true);

        $card = CreditCardFactory::create($pos, '5555444433332222', '2021', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);

        yield 'with_card_1' => [
            'account'  => $account,
            'order'    => $order,
            'txType'   => PosInterface::TX_PAY,
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
                'HashedData'           => 'apZ/1+eWzqCRk9qqACxN0bBZQ8g=',
                'RequestLanguage'      => 'tr-TR',
                'Extract'              => '',
                'CustomItems'          => '',
                'BrandNumber'          => '100',
                'CVV'                  => '122',
                'PAN'                  => '5555444433332222',
                'ExpireMonth'          => '12',
                'ExpireYear'           => '21',
                'CardHoldersName'      => 'ahmet',
            ],
        ];

        yield 'without_card_1_pre_pay' => [
            'account'  => $account,
            'order'    => $order,
            'txType'   => PosInterface::TX_PRE_PAY,
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
                'HashedData'           => 'apZ/1+eWzqCRk9qqACxN0bBZQ8g=',
                'RequestLanguage'      => 'tr-TR',
                'Extract'              => '',
                'CustomItems'          => '',
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
                    'Ptkn' => 'c5e076e7bf234a339c40afc10166c06d'
                ],
            ],
        ];
    }
}

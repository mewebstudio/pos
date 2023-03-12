<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\DataMapper;

use Mews\Pos\DataMapper\VakifBankCPPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\VakifBankCPPos;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * VakifBankCPPosRequestDataMapperTest
 */
class VakifBankCPPosRequestDataMapperTest extends TestCase
{
    /** @var VakifBankAccount */
    public $account;

    /** @var AbstractGateway */
    private $pos;

    /** @var VakifBankCPPosRequestDataMapper */
    private $requestDataMapper;
    
    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createVakifBankAccount(
            'vakifbank-cp',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            AbstractGateway::MODEL_3D_SECURE
        );

        $this->pos = PosFactory::createPosGateway($this->account);
        $this->pos->setTestMode(true);
        
        $crypt                   = PosFactory::getGatewayCrypt(VakifBankCPPos::class, new NullLogger());
        $this->requestDataMapper = new VakifBankCPPosRequestDataMapper($crypt);
    }

    /**
     * @return void
     */
    public function testAmountFormat()
    {
        $this->assertEquals('1000.00', VakifBankCPPosRequestDataMapper::amountFormat(1000));
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
        $this->assertEquals('949', $this->requestDataMapper->mapCurrency('TRY'));
        $this->assertEquals('978', $this->requestDataMapper->mapCurrency('EUR'));
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
     * @dataProvider  registerDataProvider
     */
    public function testCreate3DEnrollmentCheckData(AbstractPosAccount $account, array $order, string $txType, ?CreditCard $card, array $expectedData): void
    {
        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_PAY, $card);
        
        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData($account, $pos->getOrder(), $txType, $card);
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
            $queryParams
        );

        $this->assertSame($expected, $actualData);
    }

    public static function registerDataProvider(): iterable
    {
        $account = AccountFactory::createVakifBankAccount(
            'vakifbank-cp',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            AbstractGateway::MODEL_3D_SECURE
        );

        $order = [
            'id'          => 'order222',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'rand'        => microtime(true),
            'extraData'   => microtime(true),
            'ip'          => '127.0.0.1',
        ];

        $pos = PosFactory::createPosGateway($account);
        $pos->setTestMode(true);
        
        $card = CreditCardFactory::create($pos, '5555444433332222', '2021', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);

        yield 'with_card_1' => [
            'account'  => $account,
            'order'    => $order,
            'txType'   => AbstractGateway::TX_PAY,
            'card'     => $card,
            'expected' => [
                'HostMerchantId'       => '000000000111111',
                'MerchantPassword'     => '3XTgER89as',
                'HostTerminalId'       => 'VP999999',
                'TransactionType'      => 'Sale',
                'AmountCode'           => '949',
                'Amount'               => '100.00',
                'OrderID'              => 'order222',
                'OrderDescription'     => '',
                'IsSecure'             => 'true',
                'AllowNotEnrolledCard' => 'false',
                'SuccessUrl'           => 'https://domain.com/success',
                'FailUrl'              => 'https://domain.com/fail_url',
                'HashedData'           => '',
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
            'txType'   => AbstractGateway::TX_PRE_PAY,
            'card'     => null,
            'expected' => [
                'HostMerchantId'       => '000000000111111',
                'MerchantPassword'     => '3XTgER89as',
                'HostTerminalId'       => 'VP999999',
                'TransactionType'      => 'Auth',
                'AmountCode'           => '949',
                'Amount'               => '100.00',
                'OrderID'              => 'order222',
                'OrderDescription'     => '',
                'IsSecure'             => 'true',
                'AllowNotEnrolledCard' => 'false',
                'SuccessUrl'           => 'https://domain.com/success',
                'FailUrl'              => 'https://domain.com/fail_url',
                'HashedData'           => '',
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

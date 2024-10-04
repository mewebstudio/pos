<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestValueFormatter\PayFlexCPV4PosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueMapper\PayFlexCPV4PosRequestValueMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCard;
use Mews\Pos\Entity\Card\CreditCardInterface;
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

    private PayFlexCPV4PosRequestValueFormatter $valueFormatter;
    private PayFlexCPV4PosRequestValueMapper $valueMapper;

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

        $this->dispatcher     = $this->createMock(EventDispatcherInterface::class);
        $this->crypt          = $this->createMock(CryptInterface::class);
        $this->valueFormatter = new PayFlexCPV4PosRequestValueFormatter();
        $this->valueMapper    = new PayFlexCPV4PosRequestValueMapper();

        $this->requestDataMapper = new PayFlexCPV4PosRequestDataMapper(
            $this->valueMapper,
            $this->valueFormatter,
            $this->dispatcher,
            $this->crypt,
        );
    }

    /**
     * @dataProvider registerDataProvider
     */
    public function testCreate3DEnrollmentCheckData(AbstractPosAccount $posAccount, array $order, string $txType, ?CreditCard $creditCard, array $expectedData): void
    {
        $hashCalculationData = $expectedData;
        unset($hashCalculationData['HashedData']);

        $this->crypt->expects(self::once())
            ->method('create3DHash')
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

        yield 'without_card_1_pre_pay' => [
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
                'HashedData'           => 'apZ/1+eWzqCRk9qqACxN0bBZQ8g=',
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

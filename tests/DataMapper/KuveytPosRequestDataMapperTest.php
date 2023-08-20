<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper;

use Mews\Pos\DataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\KuveytPos;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * KuveytPosRequestDataMapperTest
 */
class KuveytPosRequestDataMapperTest extends TestCase
{
    /** @var KuveytPosAccount */
    public $threeDAccount;

    /** @var AbstractCreditCard */
    private $card;

    /** @var KuveytPos */
    private $pos;

    /** @var KuveytPosRequestDataMapper */
    private $requestDataMapper;

    private $order;

    /**
     * @return void
     *
     * @throws BankClassNullException
     * @throws BankNotFoundException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../config/pos_test.php';

        $this->threeDAccount = AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '80',
            'apiuser',
            '400235',
            'Api123'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'amount'      => 10.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'rand'        => '0.43625700 1604831630',
            'hash'        => 'zmSUxYPhmCj7QOzqpk/28LuE1Oc=',
            'ip'          => '127.0.0.1',
            'lang'        => AbstractGateway::LANG_TR,
        ];

        $this->pos = PosFactory::createPosGateway($this->threeDAccount, $config);

        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create(
            $this->pos,
            '4155650100416111',
            25,
            1,
            '123',
            'John Doe',
            AbstractCreditCard::CARD_TYPE_VISA
        );

        $crypt                   = PosFactory::getGatewayCrypt(KuveytPos::class, new NullLogger());
        $this->requestDataMapper = new KuveytPosRequestDataMapper($crypt);
    }

    /**
     * @return void
     */
    public function testAmountFormat()
    {
        $this->assertEquals(0, $this->requestDataMapper::amountFormat(0));
        $this->assertEquals(0.0, $this->requestDataMapper::amountFormat(0.0));
        $this->assertEquals(1025, $this->requestDataMapper::amountFormat(10.25));
        $this->assertEquals(1000, $this->requestDataMapper::amountFormat(10.00));
    }

    /**
     * @return void
     */
    public function testMapCurrency()
    {
        $this->assertEquals('0949', $this->requestDataMapper->mapCurrency('TRY'));
        $this->assertEquals('0978', $this->requestDataMapper->mapCurrency('EUR'));
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
     * @return void
     */
    public function testCompose3DFormData()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $order   = $this->pos->getOrder();
        $account = $this->pos->getAccount();
        $card    = $this->pos->getCard();

        $inputs = [
            'APIVersion'          => KuveytPosRequestDataMapper::API_VERSION,
            'MerchantId'          => $account->getClientId(),
            'UserName'            => $account->getUsername(),
            'CustomerId'          => $account->getCustomerId(),
            'HashData'            => 'shFFBwp4ZxLZXkHA+Z4jarwf09s=',
            'TransactionType'     => 'Sale',
            'TransactionSecurity' => 3,
            'InstallmentCount'    => $order->installment,
            'Amount'              => KuveytPosRequestDataMapper::amountFormat($order->amount),
            'DisplayAmount'       => KuveytPosRequestDataMapper::amountFormat($order->amount),
            'CurrencyCode'        => '0949',
            'MerchantOrderId'     => $order->id,
            'OkUrl'               => $order->success_url,
            'FailUrl'             => $order->fail_url,
        ];

        if ($card !== null) {
            $inputs['CardHolderName']      = $card->getHolderName();
            $inputs['CardType']            = 'Visa';
            $inputs['CardNumber']          = $card->getNumber();
            $inputs['CardExpireDateYear']  = '25';
            $inputs['CardExpireDateMonth'] = '01';
            $inputs['CardCVV2']            = $card->getCvv();
        }

        $result = $this->requestDataMapper->create3DEnrollmentCheckRequestData($account, $order, AbstractGateway::TX_PAY, $card);
        $this->assertEquals($inputs, $result);
    }

    /**
     * @dataProvider createCancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected)
    {
        $this->pos->prepare($order, AbstractGateway::TX_CANCEL);
        $actual = $this->requestDataMapper->createCancelRequestData($this->pos->getAccount(), $this->pos->getOrder());
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider createRefundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, array $expected)
    {
        $this->pos->prepare($order, AbstractGateway::TX_REFUND);
        $actual = $this->requestDataMapper->createRefundRequestData($this->pos->getAccount(), $this->pos->getOrder());
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider createStatusRequestDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected)
    {
        $this->pos->prepare($order, AbstractGateway::TX_STATUS);
        $actual = $this->requestDataMapper->createStatusRequestData($this->pos->getAccount(), $this->pos->getOrder());
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DPaymentXML()
    {
        $responseData = [
            'MD'              => '67YtBfBRTZ0XBKnAHi8c/A==',
            'VPosMessage'     => [
                'InstallmentCount'    => '0',
                'Amount'              => '100',
                'CurrencyCode'        => '0949',
                'OkUrl'               => 'http://localhost/response',
                'FailUrl'             => 'http://localhost/response',
                'OrderId'             => '86297530',
                'MerchantOrderId'     => 'Order 123',
                'TransactionSecurity' => '3',
                'MerchantId'          => '****',
                'SubMerchantId'       => '0',
                'CustomerId'          => '*****',
                'UserName'            => 'fapapi',
                'HashPassword'        => 'Hiorgg24rNeRdHUvMCg//mOJn4U=',
                'CardNumber'          => '***********1609',
            ],
            'IsEnrolled'      => 'true',
            'IsVirtual'       => 'false',
            'ResponseCode'    => '00',
            'ResponseMessage' => 'Kart doğrulandı.',
            'OrderId'         => '86297530',
            'MerchantOrderId' => 'Order 123',
            'HashData'        => 'ucejRvHjCbuPXagyoweFLnJfSJg=',
            'BusinessKey'     => '20220845654324600000140459',
        ];
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY);
        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->pos->getAccount(), $this->pos->getOrder(), AbstractGateway::TX_PAY, $responseData);

        $expectedData = $this->getSample3DPaymentXMLData($this->pos, $responseData);
        $this->assertEquals($expectedData, $actual);
    }

    private function getSample3DPaymentXMLData(KuveytPos $pos, array $responseData): array
    {
        $account = $pos->getAccount();
        $order   = $pos->getOrder();

        return [
            'APIVersion'                   => KuveytPosRequestDataMapper::API_VERSION,
            'HashData'                     => 'zC6dm10450RhS8Xi9TuBjwkLUL0=',
            'MerchantId'                   => $account->getClientId(),
            'CustomerId'                   => $account->getCustomerId(),
            'UserName'                     => $account->getUsername(),
            'CustomerIPAddress'            => $order->ip,
            'KuveytTurkVPosAdditionalData' => [
                'AdditionalData' => [
                    'Key'  => 'MD',
                    'Data' => $responseData['MD'],
                ],
            ],
            'TransactionType'              => 'Sale',
            'InstallmentCount'             => $responseData['VPosMessage']['InstallmentCount'],
            'Amount'                       => $responseData['VPosMessage']['Amount'],
            'DisplayAmount'                => 10000,
            'CurrencyCode'                 => $responseData['VPosMessage']['CurrencyCode'],
            'MerchantOrderId'              => $responseData['VPosMessage']['MerchantOrderId'],
            'TransactionSecurity'          => $responseData['VPosMessage']['TransactionSecurity'],
        ];
    }


    public static function createCancelRequestDataProvider(): iterable
    {
        yield [
            'order'    => [
                'id'              => '2023070849CD',
                'remote_order_id' => '114293600',
                'ref_ret_num'     => '318923298433',
                'auth_code'       => '241839',
                'trans_id'        => '298433',
                'amount'          => 1.01,
                'currency'        => 'TRY',
            ],
            'expected' => [
                'IsFromExternalNetwork' => true,
                'BusinessKey'           => 0,
                'ResourceId'            => 0,
                'ActionId'              => 0,
                'LanguageId'            => 0,
                'CustomerId'            => '400235',
                'MailOrTelephoneOrder'  => true,
                'Amount'                => 101,
                'MerchantId'            => '80',
                'OrderId'               => '114293600',
                'RRN'                   => '318923298433',
                'Stan'                  => '298433',
                'ProvisionNumber'       => '241839',
                'TransactionType'       => 0,
                'VPosMessage'           => [
                    'APIVersion'                       => '1.0.0',
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => 'Om26dd7XpVGq0KyTJBM3TUH4fSU=',
                    'MerchantId'                       => '80',
                    'SubMerchantId'                    => 0,
                    'CustomerId'                       => '400235',
                    'UserName'                         => 'apiuser',
                    'CardType'                         => 'Visa',
                    'BatchID'                          => 0,
                    'TransactionType'                  => 'SaleReversal',
                    'InstallmentCount'                 => 0,
                    'Amount'                           => 101,
                    'DisplayAmount'                    => 101,
                    'CancelAmount'                     => 101,
                    'MerchantOrderId'                  => '2023070849CD',
                    'FECAmount'                        => 0,
                    'CurrencyCode'                     => '0949',
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
            ],
        ];
    }

    public static function createRefundRequestDataProvider(): iterable
    {
        yield [
            'order'    => [
                'id'              => '2023070849CD',
                'remote_order_id' => '114293600',
                'ref_ret_num'     => '318923298433',
                'auth_code'       => '241839',
                'trans_id'        => '298433',
                'amount'          => 1.01,
                'currency'        => 'TRY',
            ],
            'expected' => [
                'IsFromExternalNetwork' => true,
                'BusinessKey'           => 0,
                'ResourceId'            => 0,
                'ActionId'              => 0,
                'LanguageId'            => 0,
                'CustomerId'            => '400235',
                'MailOrTelephoneOrder'  => true,
                'Amount'                => 101,
                'MerchantId'            => '80',
                'OrderId'               => '114293600',
                'RRN'                   => '318923298433',
                'Stan'                  => '298433',
                'ProvisionNumber'       => '241839',
                'TransactionType'       => 0,
                'VPosMessage'           => [
                    'APIVersion'                       => '1.0.0',
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => 'Om26dd7XpVGq0KyTJBM3TUH4fSU=',
                    'MerchantId'                       => '80',
                    'SubMerchantId'                    => 0,
                    'CustomerId'                       => '400235',
                    'UserName'                         => 'apiuser',
                    'CardType'                         => 'Visa',
                    'BatchID'                          => 0,
                    'TransactionType'                  => 'PartialDrawback',
                    'InstallmentCount'                 => 0,
                    'Amount'                           => 101,
                    'DisplayAmount'                    => 0,
                    'CancelAmount'                     => 101,
                    'MerchantOrderId'                  => '2023070849CD',
                    'FECAmount'                        => 0,
                    'CurrencyCode'                     => '0949',
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
            ],
        ];
    }

    public static function createStatusRequestDataProvider(): iterable
    {
        $startDate = new \DateTime('2022-07-08T22:44:31');
        $endDate = new \DateTime('2023-07-08T22:44:31');
        yield [
            'order'    => [
                'id'       => '2023070849CD',
                'currency' => 'TRY',
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'expected' => [
                'IsFromExternalNetwork' => true,
                'BusinessKey'           => 0,
                'ResourceId'            => 0,
                'ActionId'              => 0,
                'LanguageId'            => 0,
                'CustomerId'            => null,
                'MailOrTelephoneOrder'  => true,
                'Amount'                => 0,
                'MerchantId'            => '80',
                'OrderId'               => 0,
                'TransactionType'       => 0,
                'VPosMessage'           => [
                    'APIVersion'                       => '1.0.0',
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => 'RwQ5Sfc6D4Ovy7jvQgf5jGA/rOk=',
                    'MerchantId'                       => '80',
                    'SubMerchantId'                    => 0,
                    'CustomerId'                       => '400235',
                    'UserName'                         => 'apiuser',
                    'CardType'                         => 'Visa',
                    'BatchID'                          => 0,
                    'TransactionType'                  => 'GetMerchantOrderDetail',
                    'InstallmentCount'                 => 0,
                    'Amount'                           => 0,
                    'DisplayAmount'                    => 0,
                    'CancelAmount'                     => 0,
                    'MerchantOrderId'                  => '2023070849CD',
                    'FECAmount'                        => 0,
                    'CurrencyCode'                     => '0949',
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
                'MerchantOrderId'       => '2023070849CD',
                'StartDate'             => '2022-07-08T22:44:31',
                'EndDate'               => '2023-07-08T22:44:31',
            ],
        ];
    }
}

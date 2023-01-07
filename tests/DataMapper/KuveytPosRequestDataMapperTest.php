<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper;

use Mews\Pos\DataMapper\KuveytPosRequestDataMapper;
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

        $this->pos = PosFactory::createPosGateway($this->threeDAccount);

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

        $crypt = PosFactory::getGatewayCrypt(KuveytPos::class, new NullLogger());
        $this->requestDataMapper = new KuveytPosRequestDataMapper($crypt);
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
     * @testWith ["0", 0]
     *           ["1", 0]
     *           ["2", 2]
     *           [2, 2]
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

        if ($card) {
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
}

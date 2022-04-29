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
            'lang'        => KuveytPos::LANG_TR,
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

        $this->requestDataMapper = new KuveytPosRequestDataMapper();
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
            'HashData'            => $this->requestDataMapper->create3DHash($account, $order, 'Auth'),
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
        $txType = 'Sale';
        $result = $this->requestDataMapper->create3DEnrollmentCheckRequestData($account, $order, $txType, $card);
        $this->assertEquals($inputs, $result);
    }

    /**
     * @return void
     */
    public function testCreate3DPaymentXML()
    {
        $txType = 'Sale';
        $responseData = [
            'MD'          => '67YtBfBRTZ0XBKnAHi8c/A==',
            'VPosMessage' => [
                'TransactionType'     => $txType,
                'InstallmentCount'    => '0',
                'Amount'              => '100',
                'DisplayAmount'       => '100',
                'CurrencyCode'        => '0949',
                'MerchantOrderId'     => 'Order 123',
                'TransactionSecurity' => '3',
            ],
        ];
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY);
        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->pos->getAccount(), $this->pos->getOrder(), $txType, $responseData);

        $expectedData = $this->getSample3DPaymentXMLData($this->pos, $txType, $responseData);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DHashForProvision()
    {
        $order   = [
            'id'          => 'ORDER-123',
            'amount'      => 72.56,
            'currency'    => 'TRY',
            'installment' => '0',
            'success_url' => 'http://localhost:44785/Home/Success',
            'fail_url'    => 'http://localhost:44785/Home/Fail',
        ];
        $hash    = 'Bf+hZf2c1gf1pTXnEaSGxDpGRr0=';
        $this->pos->prepare($order, AbstractGateway::TX_PAY);
        $actual = $this->requestDataMapper->create3DHash($this->pos->getAccount(), $this->pos->getOrder(), 'Sale', true);
        $this->assertEquals($hash, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DHashForAuthorization()
    {
        $order   = [
            'id'          => 'ORDER-123',
            'amount'      => 72.56,
            'currency'    => 'TRY',
            'installment' => '0',
            'success_url' => 'http://localhost:44785/Home/Success',
            'fail_url'    => 'http://localhost:44785/Home/Fail',
        ];
        $hash    = 'P3a0zjAklu2g8XDJfTx2qvwHH8g=';
        $this->pos->prepare($order, AbstractGateway::TX_PAY);
        $actual = $this->requestDataMapper->create3DHash($this->pos->getAccount(), $this->pos->getOrder(), 'Sale');
        $this->assertEquals($hash, $actual);
    }

    /**
     * @param KuveytPos $pos
     * @param string    $txType
     * @param           $responseData
     *
     * @return array
     */
    private function getSample3DPaymentXMLData(KuveytPos $pos, string $txType, $responseData): array
    {
        $account = $pos->getAccount();
        $order   = $pos->getOrder();

        $hash    = $this->requestDataMapper->create3DHash($pos->getAccount(), $pos->getOrder(), $txType, true);

        return [
            'APIVersion'                   => KuveytPosRequestDataMapper::API_VERSION,
            'HashData'                     => $hash,
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
            'TransactionType'              => $responseData['VPosMessage']['TransactionType'],
            'InstallmentCount'             => $responseData['VPosMessage']['InstallmentCount'],
            'Amount'                       => $responseData['VPosMessage']['Amount'],
            'DisplayAmount'                => $responseData['VPosMessage']['DisplayAmount'],
            'CurrencyCode'                 => $responseData['VPosMessage']['CurrencyCode'],
            'MerchantOrderId'              => $responseData['VPosMessage']['MerchantOrderId'],
            'TransactionSecurity'          => $responseData['VPosMessage']['TransactionSecurity'],
        ];
    }
}

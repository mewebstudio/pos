<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\VakifBankPos;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * VakifBankPosTest
 */
class VakifBankPosTest extends TestCase
{
    /**
     * @var VakifBankAccount
     */
    private $account;
    /**
     * @var VakifBankPos
     */
    private $pos;
    private $config;

    /**
     * @var AbstractCreditCard
     */
    private $card;

    /** @var array */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createVakifBankAccount(
            'vakifbank',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            AbstractGateway::MODEL_3D_SECURE
        );


        $this->order = [
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

        $this->pos = PosFactory::createPosGateway($this->account);

        $this->pos->setTestMode(true);
        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '2021', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
    }

    /**
     * @return void
     */
    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
    }

    /**
     * @return void
     */
    public function testPrepare()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertSame('949', $this->pos->getOrder()->currency);
        $this->assertEquals($this->card, $this->pos->getCard());

        $this->pos->prepare($this->order, AbstractGateway::TX_POST_PAY);
        $this->assertSame('949', $this->pos->getOrder()->currency);
    }

    /**
     * @covers \Mews\Pos\Gateways\VakifBankPos::map3DPaymentData
     *
     * @return void
     */
    public function testMap3DPaymentData3DSuccess()
    {
        $card  = $this->card;
        $order = $this->order;
        $this->pos->prepare($order, AbstractGateway::TX_PAY);
        $preparedOrder  = (array) $this->pos->getOrder();
        $threeDResponse = [
            'MerchantId'                => $this->account->getClientId(),
            'SubMerchantNo'             => $this->account->getSubMerchantId(),
            'SubMerchantName'           => null,
            'SubMerchantNumber'         => null,
            'PurchAmount'               => $preparedOrder['amount'] * 100,
            'PurchCurrency'             => $preparedOrder['currency'],
            'VerifyEnrollmentRequestId' => $preparedOrder['id'],
            'SessionInfo'               => $preparedOrder['extraData'],
            'InstallmentCount'          => null,
            'Pan'                       => $card->getNumber(),
            'Expiry'                    => 'cv',
            'Xid'                       => md5(uniqid(rand(), true)),
            'Status'                    => 'Y',
            'Cavv'                      => 'AAABBBBBBBBBBBBBBBIIIIII=',
            'Eci'                       => '02',
            'ExpSign'                   => null,
            'ErrorCode'                 => null,
            'ErrorMessage'              => null,
        ];

        $provisionResponse = [
            'MerchantId'              => $this->account->getClientId(),
            'TerminalNo'              => $this->account->getTerminalId(),
            'TransactionType'         => 'Sale',
            'TransactionId'           => $preparedOrder['extraData'], //todo why it is equal to extraData?
            'ResultCode'              => '0000',
            'ResultDetail'            => 'İŞLEM BAŞARILI',
            'CustomItems'             => (object) [],
            'InstallmentTable'        => null,
            'CampaignResult'          => null,
            'AuthCode'                => '822641',
            'HostDate'                => '20220404123456',
            'Rrn'                     => '209411062014',
            'CurrencyAmount'          => $preparedOrder['amount'],
            'CurrencyCode'            => $preparedOrder['currency'],
            'OrderId'                 => $preparedOrder['id'],
            'TLAmount'                => $preparedOrder['amount'],
            'ECI'                     => '02',
            'ThreeDSecureType'        => '2',
            'TransactionDeviceSource' => '0',
            'BatchNo'                 => '1',
        ];

        $method  = $this->getMethod('map3DPaymentData');
        $result = $method->invoke($this->pos, $threeDResponse, (object) $provisionResponse);

        $expected = [
            'eci'              => $provisionResponse['ECI'],
            'cavv'             => $threeDResponse['Cavv'],
            'auth_code'        => $provisionResponse['AuthCode'],
            'order_id'         => $provisionResponse['OrderId'],
            'status'           => 'approved',
            'status_detail'    => $provisionResponse['ResultDetail'],
            'error_code'       => null,
            'error_message'    => null,
            'all'              => (object) $provisionResponse,
            '3d_all'           => $threeDResponse,
            'id'               => $provisionResponse['AuthCode'],
            'trans_id'         => $provisionResponse['TransactionId'],
            'host_ref_num'     => $provisionResponse['Rrn'],
            'transaction'      => $provisionResponse['TransactionType'],
            'transaction_type' => $provisionResponse['TransactionType'],
            'response'         => null,
            'proc_return_code' => $provisionResponse['ResultCode'],
            'code'             => $provisionResponse['ResultCode'],
        ];

        $this->assertEquals($expected, (array) $result);
    }

    /**
     * @covers \Mews\Pos\Gateways\VakifBankPos::map3DPaymentData
     *
     * @return void
     */
    public function testMap3DPaymentData3DFail()
    {
        $card  = $this->card;
        $order = $this->order;
        $this->pos->prepare($order, AbstractGateway::TX_PAY);
        $preparedOrder  = (array) $this->pos->getOrder();
        $threeDResponse = [
            'MerchantId'                => $this->account->getClientId(),
            'SubMerchantNo'             => $this->account->getSubMerchantId(),
            'SubMerchantName'           => null,
            'SubMerchantNumber'         => null,
            'PurchAmount'               => $preparedOrder['amount'] * 100,
            'PurchCurrency'             => $preparedOrder['currency'],
            'VerifyEnrollmentRequestId' => $preparedOrder['id'],
            'SessionInfo'               => $preparedOrder['extraData'],
            'InstallmentCount'          => null,
            'Pan'                       => $card->getNumber(),
            'Expiry'                    => 'hj',
            'Xid'                       => md5(uniqid(rand(), true)),
            'Status'                    => 'E', //diger hata durumlari N, U
            'Cavv'                      => 'AAABBBBBBBBBBBBBBBIIIIII=',
            'Eci'                       => '02',
            'ExpSign'                   => '',
            'ErrorCode'                 => '1105',
            'ErrorMessage'              => 'Üye isyeri IP si sistemde tanimli degil',
        ];

        $provisionResponse = [];

        $method  = $this->getMethod('map3DPaymentData');
        $result = $method->invoke($this->pos, $threeDResponse, (object) $provisionResponse);

        $expected = [
            'eci'              => $threeDResponse['Eci'],
            'cavv'             => $threeDResponse['Cavv'],
            'auth_code'        => null,
            'order_id'         => $threeDResponse['VerifyEnrollmentRequestId'],
            'status'           => 'declined',
            'status_detail'    => null,
            'error_code'       => $threeDResponse['ErrorCode'],
            'error_message'    => $threeDResponse['ErrorMessage'],
            'all'              => (object) $provisionResponse,
            '3d_all'           => $threeDResponse,
            'id'               => null,
            'trans_id'         => null,
            'host_ref_num'     => null,
            'transaction_type' => 'Sale',
            'transaction'      => 'Sale',
            'proc_return_code' => null,
            'code'             => null,
            'response'         => null,
        ];

        $this->assertEquals($expected, (array) $result);
    }

    private static function getMethod(string $name): \ReflectionMethod
    {
        $class = new ReflectionClass(VakifBankPos::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}

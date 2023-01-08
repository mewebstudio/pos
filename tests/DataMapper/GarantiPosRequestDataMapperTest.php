<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper;

use Mews\Pos\DataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\GarantiPos;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * GarantiPosRequestDataMapperTest
 */
class GarantiPosRequestDataMapperTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $threeDAccount;

    /** @var GarantiPos */
    private $pos;

    /** @var AbstractCreditCard */
    private $card;

    /** @var GarantiPosRequestDataMapper */
    private $requestDataMapper;

    private $order;
    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->threeDAccount = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            AbstractGateway::MODEL_3D_SECURE,
            '12345678',
            'PROVRFN',
            '123qweASD/'
        );

        $this->order = [
            'id'          => 'order222',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'ip'          => '156.155.154.153',
        ];

        $this->pos = PosFactory::createPosGateway($this->threeDAccount);
        $this->pos->setTestMode(true);
        $crypt = PosFactory::getGatewayCrypt(GarantiPos::class, new NullLogger());
        $this->requestDataMapper = new GarantiPosRequestDataMapper($crypt);
        $this->requestDataMapper->setTestMode(true);
        $this->card              = CreditCardFactory::create($this->pos, '5555444433332222', '22', '01', '123', 'ahmet');
    }

    /**
     * @return void
     */
    public function testAmountFormat()
    {
        $this->assertEquals(100000, $this->requestDataMapper::amountFormat(1000));
        $this->assertEquals(100000, $this->requestDataMapper::amountFormat(1000.00));
        $this->assertEquals(100001, $this->requestDataMapper::amountFormat(1000.01));
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
     * @testWith ["0", ""]
     *           ["1", ""]
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
    public function testCreateNonSecurePostAuthPaymentRequestData()
    {
        $this->order['ref_ret_num'] = '831803579226';
        $order = $this->order;
        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_POST_PAY);

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleNonSecurePaymentPostRequestData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData()
    {
        $order = $this->order;
        $pos   = $this->pos;
        $card  = $this->card;
        $pos->prepare($order, AbstractGateway::TX_PAY, $card);

        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($pos->getAccount(), $pos->getOrder(), AbstractGateway::TX_PAY, $card);

        $expectedData = $this->getSampleNonSecurePaymentRequestData($pos->getAccount(), $pos->getOrder(), $pos->getCard());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData()
    {
        $order = [
            'id'          => '2020110828BC',
            'currency'    => 'TRY',
            'amount'      => '1.00',
            'ref_ret_num' => '831803579226',
        ];
        $pos   = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_CANCEL);

        $actual = $this->requestDataMapper->createCancelRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleCancelXMLData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateHistoryRequestData()
    {
        $order = $this->order;
        $pos   = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_HISTORY);
        $actual = $this->requestDataMapper->createHistoryRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleHistoryRequestData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DPaymentRequestData()
    {
        $pos = $this->pos;
        $order = $this->order;
        $responseData = $this->getSample3DResponseData();
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actual = $this->requestDataMapper->create3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), '', $responseData);

        $expectedData = $this->getSample3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), $responseData);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testGet3DFormData()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $pos = $this->pos;
        $account = $pos->getAccount();
        $order = $pos->getOrder();
        $card = $pos->getCard();
        $gatewayURL = $this->config['banks'][$this->threeDAccount->getBank()]['urls']['gateway']['test'];
        $inputs = [
            'secure3dsecuritylevel' => '3D',
            'mode'                  => 'TEST',
            'apiversion'            => 'v0.01',
            'terminalprovuserid'    => $account->getUsername(),
            'terminaluserid'        => $account->getUsername(),
            'terminalmerchantid'    => $account->getClientId(),
            'terminalid'            => $account->getTerminalId(),
            'txntype'               => 'sales',
            'txnamount'             => 10025,
            'txncurrencycode'       => '949',
            'txninstallmentcount'   => '',
            'orderid'               => $this->order['id'],
            'successurl'            => $this->order['success_url'],
            'errorurl'              => $this->order['fail_url'],
            'customeremailaddress'  => $order->email,
            'customeripaddress'     => $this->order['ip'],
            'secure3dhash'          => '1D319D5EA945F5730FF5BCC970FF96690993F4BD',
            'cardnumber'            => $card->getNumber(),
            'cardexpiredatemonth'   => '01',
            'cardexpiredateyear'    => '22',
            'cardcvv2'              => $card->getCvv(),
        ];

        $form = [
            'inputs' => $inputs,
            'gateway' => $gatewayURL,
        ];

        //test without card
        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->pos->getAccount(),
            $this->pos->getOrder(),
            AbstractGateway::TX_PAY,
            $gatewayURL,
            $this->card
        ));
    }

    /**
     * @return void
     */
    public function testCreateStatusRequestData()
    {
        $order = [
            'id'       => '2020110828BC',
            'currency' => 'TRY',
        ];

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_STATUS);

        $actualData = $this->requestDataMapper->createStatusRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleStatusRequestData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData()
    {
        $order = [
            'id'          => '2020110828BC',
            'currency'    => 'TRY',
            'amount'      => 123.1,
            'ref_ret_num' => '831803579226',
        ];

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_REFUND);

        $actual = $this->requestDataMapper->createRefundRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleRefundXMLData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @param GarantiPosAccount $account
     * @param                   $order
     * @param array             $responseData
     *
     * @return array
     */
    private function getSample3DPaymentRequestData(AbstractPosAccount $account, $order, array $responseData): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => 'v0.01',
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '00CD5B6C29D4CEA1F3002D785A9F9B09974AD51D',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $responseData['customeripaddress'],
                'EmailAddress' => $responseData['customeremailaddress'],
            ],
            'Order'       => [
                'OrderID'     => $responseData['orderid'],
                'AddressList' => [
                    'Address' => [
                        'Type'        => 'B',
                        'Name'        => $order->name,
                        'LastName'    => '',
                        'Company'     => '',
                        'Text'        => '',
                        'District'    => '',
                        'City'        => '',
                        'PostalCode'  => '',
                        'Country'     => '',
                        'PhoneNumber' => '',
                    ],
                ],
            ],
            'Transaction' => [
                'Type'                  => $responseData['txntype'],
                'InstallmentCnt'        => '',
                'Amount'                => $responseData['txnamount'],
                'CurrencyCode'          => $responseData['txncurrencycode'],
                'CardholderPresentCode' => '13',
                'MotoInd'               => 'N',
                'Secure3D'              => [
                    'AuthenticationCode' => $responseData['cavv'],
                    'SecurityLevel'      => $responseData['eci'],
                    'TxnID'              => $responseData['xid'],
                    'Md'                 => $responseData['md'],
                ],
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     * @param                   $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $account, $order): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => 'v0.01',
            'Terminal'    => [
                'ProvUserID' => $account->getRefundUsername(),
                'UserID'     => $account->getRefundUsername(),
                'HashData'   => '8DD74209DEEB7D333105E1C69998A827419A3B04',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Order'       => [
                'OrderID' => $order->id,
            ],
            'Transaction' => [
                'Type'                  => 'void',
                'InstallmentCnt'        => '',
                'Amount'                => 100,
                'CurrencyCode'          => '949',
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
                'OriginalRetrefNum'     => $order->ref_ret_num,
            ],
        ];
    }

    /**
     * @param GarantiPosAccount  $account
     * @param                    $order
     * @param AbstractCreditCard $card
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $account, $order, AbstractCreditCard $card): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => 'v0.01',
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '3732634F78053D42304B0966E263629FE44E258B',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Card'        => [
                'Number'     => $card->getNumber(),
                'ExpireDate' => '0122',
                'CVV2'       => $card->getCvv(),
            ],
            'Order'       => [
                'OrderID'     => $order->id,
                'AddressList' => [
                    'Address' => [
                        'Type'        => 'B',
                        'Name'        => $order->name,
                        'LastName'    => '',
                        'Company'     => '',
                        'Text'        => '',
                        'District'    => '',
                        'City'        => '',
                        'PostalCode'  => '',
                        'Country'     => '',
                        'PhoneNumber' => '',
                    ],
                ],
            ],
            'Transaction' => [
                'Type'                  => 'sales',
                'InstallmentCnt'        => '',
                'Amount'                => 10025,
                'CurrencyCode'          => 949,
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     * @param                   $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => 'v0.01',
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '00CD5B6C29D4CEA1F3002D785A9F9B09974AD51D',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Order'       => [
                'OrderID' => $order->id,
            ],
            'Transaction' => [
                'Type'              => 'postauth',
                'Amount'            => 10025,
                'CurrencyCode'      => '949',
                'OriginalRetrefNum' => $order->ref_ret_num,
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     * @param                   $order
     *
     * @return array
     */
    private function getSampleStatusRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => 'v0.01',
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '8DD74209DEEB7D333105E1C69998A827419A3B04',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Order'       => [
                'OrderID' => $order->id,
            ],
            'Transaction' => [
                'Type'                  => 'orderinq',
                'InstallmentCnt'        => '',
                'Amount'                => 100,
                'CurrencyCode'          => '949',
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     * @param                   $order
     *
     * @return array
     */
    private function getSampleRefundXMLData(AbstractPosAccount $account, $order): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => 'v0.01',
            'Terminal'    => [
                'ProvUserID' => $account->getRefundUsername(),
                'UserID'     => $account->getRefundUsername(),
                'HashData'   => '01EA91D49CC3039D38894FBB6303EFDAAD7F964D',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Order'       => [
                'OrderID' => $order->id,
            ],
            'Transaction' => [
                'Type'                  => 'refund',
                'InstallmentCnt'        => '',
                'Amount'                => GarantiPosRequestDataMapper::amountFormat($order->amount),
                'CurrencyCode'          => '949',
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
                'OriginalRetrefNum'     => $order->ref_ret_num,
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     * @param                    $order
     *
     * @return array
     */
    private function getSampleHistoryRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => 'v0.01',
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '19460C02029180F8F7E19A4835D62E4118600A34',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
               'IPAddress'    => $order->ip,
               'EmailAddress' => $order->email,
            ],
            'Order'       => [
                'OrderID' => $order->id,
            ],
            'Transaction' => [
                'Type'                  => 'orderhistoryinq',
                'InstallmentCnt'        => '',
                'Amount'                => 100,
                'CurrencyCode'          => '949',
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
            ],
        ];
    }

    /**
     * @return string[]
     */
    private function getSample3DResponseData(): array
    {
        return [
            'orderid'              => '2020110828BC',
            'md'                   => '1',
            'xid'                  => '100000005xid',
            'eci'                  => '100000005eci',
            'cavv'                 => 'cavv',
            'txncurrencycode'      => 'txncurrencycode',
            'txnamount'            => 'txnamount',
            'txntype'              => 'txntype',
            'customeripaddress'    => 'customeripaddress',
            'customeremailaddress' => 'customeremailaddress',
        ];
    }
}

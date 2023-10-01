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
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * GarantiPosRequestDataMapperTest
 */
class GarantiPosRequestDataMapperTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $account;

    /** @var AbstractCreditCard */
    private $card;

    /** @var GarantiPosRequestDataMapper */
    private $requestDataMapper;

    private $order;

    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $this->account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            PosInterface::MODEL_3D_SECURE,
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
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'ip'          => '156.155.154.153',
        ];

        $pos = PosFactory::createPosGateway($this->account, $this->config, new EventDispatcher());

        $crypt                   = PosFactory::getGatewayCrypt(GarantiPos::class, new NullLogger());
        $this->requestDataMapper = new GarantiPosRequestDataMapper($crypt);
        $this->requestDataMapper->setTestMode(true);

        $this->card = CreditCardFactory::create($pos, '5555444433332222', '22', '01', '123', 'ahmet');
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
        $this->assertEquals('949', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertEquals('978', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_EUR));
    }

    /**
     * @param string|int|null $installment
     * @param string|int      $expected
     *
     * @testWith ["0", ""]
     *           ["1", ""]
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
    public function testCreateNonSecurePostAuthPaymentRequestData()
    {
        $this->order['ref_ret_num'] = '831803579226';

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $this->order);

        $expectedData = $this->getSampleNonSecurePaymentPostRequestData($this->account, $this->order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData()
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $this->order, PosInterface::TX_PAY, $this->card);

        $expectedData = $this->getSampleNonSecurePaymentRequestData($this->account, $this->order, $this->card);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData()
    {
        $order = [
            'id'          => '2020110828BC',
            'ip'          => '127.15.15.1',
            'currency'    => PosInterface::CURRENCY_TRY,
            'amount'      => '1.00',
            'ref_ret_num' => '831803579226',
            'installment' => 0,
            'email'       => 'email@example.com',
        ];

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $expectedData = $this->getSampleCancelXMLData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateHistoryRequestData()
    {
        $this->order['amount'] = 1;

        $actual = $this->requestDataMapper->createHistoryRequestData($this->account, $this->order);

        $expectedData = $this->getSampleHistoryRequestData($this->account, $this->order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(GarantiPosAccount $account, array $order, array $responseData, array $expected)
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, '', $responseData);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testGet3DFormData()
    {
        $account    = $this->account;
        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d'];
        $inputs     = [
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
            'customeremailaddress'  => $this->order['email'],
            'customeripaddress'     => $this->order['ip'],
            'secure3dhash'          => '1D319D5EA945F5730FF5BCC970FF96690993F4BD',
            'cardnumber'            => $this->card->getNumber(),
            'cardexpiredatemonth'   => '01',
            'cardexpiredateyear'    => '22',
            'cardcvv2'              => $this->card->getCvv(),
        ];

        $form = [
            'inputs'  => $inputs,
            'method'  => 'POST',
            'gateway' => $gatewayURL,
        ];

        //test without card
        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->account,
            $this->order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_PAY,
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
            'id'          => '2020110828BC',
            'ip'          => '127.15.15.1',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'amount'      => 1,
            'email'       => 'email@example.com',
        ];

        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $expectedData = $this->getSampleStatusRequestData($this->account, $order);
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData()
    {
        $order = [
            'id'          => '2020110828BC',
            'ip'          => '127.15.15.1',
            'currency'    => PosInterface::CURRENCY_TRY,
            'amount'      => 123.1,
            'ref_ret_num' => '831803579226',
            'email'       => 'email@example.com',
            'installment' => 0,
        ];

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $expectedData = $this->getSampleRefundXMLData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @param GarantiPosAccount $account
     * @param array             $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $account, array $order): array
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
                'IPAddress'    => $order['ip'],
                'EmailAddress' => $order['email'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => 'void',
                'InstallmentCnt'        => '',
                'Amount'                => 100,
                'CurrencyCode'          => '949',
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
                'OriginalRetrefNum'     => $order['ref_ret_num'],
            ],
        ];
    }

    /**
     * @param GarantiPosAccount  $account
     * @param array              $order
     * @param AbstractCreditCard $card
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, AbstractCreditCard $card): array
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
                'IPAddress'    => $order['ip'],
                'EmailAddress' => $order['email'],
            ],
            'Card'        => [
                'Number'     => $card->getNumber(),
                'ExpireDate' => '0122',
                'CVV2'       => $card->getCvv(),
            ],
            'Order'       => [
                'OrderID'     => $order['id'],
                'AddressList' => [
                    'Address' => [
                        'Type'        => 'B',
                        'Name'        => $order['name'],
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
     * @param array             $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $account, array $order): array
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
                'IPAddress'    => $order['ip'],
                'EmailAddress' => $order['email'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'              => 'postauth',
                'Amount'            => 10025,
                'CurrencyCode'      => '949',
                'OriginalRetrefNum' => $order['ref_ret_num'],
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     * @param array             $order
     *
     * @return array
     */
    private function getSampleStatusRequestData(AbstractPosAccount $account, array $order): array
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
                'IPAddress'    => $order['ip'],
                'EmailAddress' => $order['email'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
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
     * @param array             $order
     *
     * @return array
     */
    private function getSampleRefundXMLData(AbstractPosAccount $account, array $order): array
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
                'IPAddress'    => $order['ip'],
                'EmailAddress' => $order['email'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => 'refund',
                'InstallmentCnt'        => '',
                'Amount'                => GarantiPosRequestDataMapper::amountFormat($order['amount']),
                'CurrencyCode'          => '949',
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
                'OriginalRetrefNum'     => $order['ref_ret_num'],
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     * @param array             $order
     *
     * @return array
     */
    private function getSampleHistoryRequestData(AbstractPosAccount $account, array $order): array
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
                'IPAddress'    => $order['ip'],
                'EmailAddress' => $order['email'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
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

    public static function create3DPaymentRequestDataDataProvider(): array
    {
        $account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            PosInterface::MODEL_3D_SECURE,
            '12345678',
            'PROVRFN',
            '123qweASD/'
        );

        $order = [
            'id'          => '2020110828BC',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'ip'          => '156.155.154.153',
        ];

        return [
            [
                'account'      => $account,
                'order'        => $order,
                'responseData' => [
                    'orderid'              => '2020110828BC',
                    'md'                   => '1',
                    'xid'                  => '100000005xid',
                    'eci'                  => '100000005eci',
                    'cavv'                 => 'cavv',
                    'txncurrencycode'      => '949',
                    'txnamount'            => '100.25',
                    'txntype'              => 'sales',
                    'customeripaddress'    => '127.0.0.1',
                    'customeremailaddress' => 'test@test.com',
                ],
                'expected' => [
                    'Mode'        => 'TEST',
                    'Version'     => 'v0.01',
                    'Terminal'    => [
                        'ProvUserID' => $account->getUsername(),
                        'UserID'     => $account->getUsername(),
                        'HashData'   => 'EA03A05EB5FA82B6CEF6CE456B94C0A0ACBDDAD8',
                        'ID'         => $account->getTerminalId(),
                        'MerchantID' => $account->getClientId(),
                    ],
                    'Customer'    => [
                        'IPAddress'    => '127.0.0.1',
                        'EmailAddress' => 'test@test.com',
                    ],
                    'Order'       => [
                        'OrderID'     => '2020110828BC',
                        'AddressList' => [
                            'Address' => [
                                'Type'        => 'B',
                                'Name'        => 'siparis veren',
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
                        'Amount'                => '100.25',
                        'CurrencyCode'          => '949',
                        'CardholderPresentCode' => '13',
                        'MotoInd'               => 'N',
                        'Secure3D'              => [
                            'AuthenticationCode' => 'cavv',
                            'SecurityLevel'      => '100000005eci',
                            'TxnID'              => '100000005xid',
                            'Md'                 => '1',
                        ],
                    ],
                ],
            ],
        ];
    }
}

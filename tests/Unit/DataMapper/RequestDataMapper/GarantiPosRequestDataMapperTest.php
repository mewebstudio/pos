<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\DataMapper\RequestDataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\GarantiPosRequestDataMapper
 */
class GarantiPosRequestDataMapperTest extends TestCase
{
    private GarantiPosAccount $account;

    private CreditCardInterface $card;

    private GarantiPosRequestDataMapper $requestDataMapper;

    private $order;

    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../../../config/pos_test.php';

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
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'ip'          => '156.155.154.153',
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos = PosFactory::createPosGateway($this->account, $this->config, $dispatcher);

        $crypt                   = CryptFactory::createGatewayCrypt(GarantiPos::class, new NullLogger());
        $this->requestDataMapper = new GarantiPosRequestDataMapper($dispatcher, $crypt);
        $this->requestDataMapper->setTestMode(true);

        $this->card = CreditCardFactory::create($pos, '5555444433332222', '22', '01', '123', 'ahmet');
    }

    /**
     * @return void
     */
    public function testFormatAmount()
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('formatAmount');
        $method->setAccessible(true);
        $this->assertSame(100000, $method->invokeArgs($this->requestDataMapper, [1000]));
        $this->assertSame(100000, $method->invokeArgs($this->requestDataMapper, [1000.00]));
        $this->assertSame(100001, $method->invokeArgs($this->requestDataMapper, [1000.01]));
    }

    /**
     * @return void
     */
    public function testMapCurrency()
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
     * @testWith ["0", ""]
     *           ["1", ""]
     *           ["2", "2"]
     *           [2, "2"]
     *
     * @return void
     */
    public function testMapInstallment($installment, $expected)
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('mapInstallment');
        $method->setAccessible(true);
        $this->assertSame($expected, $method->invokeArgs($this->requestDataMapper, [$installment]));
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
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $this->order, PosInterface::TX_TYPE_PAY, $this->card);

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
            'apiversion'            => '512',
            'terminalprovuserid'    => $account->getUsername(),
            'terminaluserid'        => $account->getUsername(),
            'terminalmerchantid'    => $account->getClientId(),
            'terminalid'            => $account->getTerminalId(),
            'txntype'               => 'sales',
            'txnamount'             => '10025',
            'txncurrencycode'       => '949',
            'txninstallmentcount'   => '',
            'orderid'               => $this->order['id'],
            'successurl'            => $this->order['success_url'],
            'errorurl'              => $this->order['fail_url'],
            'customeripaddress'     => $this->order['ip'],
            'secure3dhash'          => '372D6CB20B2B699D0A6667DFF46E3AA8CF3F9D8C2BB69A7C411895151FFCFAAB5277CCFE3B3A06035FEEFBFBFD40C79DBE51DBF867D0A24B37335A28F0CEFDE2',
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
            PosInterface::TX_TYPE_PAY,
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
        ];

        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $expectedData = $this->getSampleStatusRequestData($this->account, $order);
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @dataProvider refundOrderDataProvider
     */
    public function testCreateRefundRequestData(GarantiPosAccount $account, array $order, array $expectedData)
    {
        $actual = $this->requestDataMapper->createRefundRequestData($account, $order);

        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @param GarantiPosAccount $account
     * @param                   $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $account, array $order): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => '512',
            'Terminal'    => [
                'ProvUserID' => $account->getRefundUsername(),
                'UserID'     => $account->getRefundUsername(),
                'HashData'   => '35E8410A78E24949D78F5E025B5E05AF470B01385A2ECBFEE6C5B3CDACFF78011D387ECAFDCE4B8453D80D35C2F344F3DAA6F2EF9143079F64DE88401EC5E4F5',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order['ip'],
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
     * @param GarantiPosAccount   $account
     * @param array               $order
     * @param CreditCardInterface $card
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, CreditCardInterface $card): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => '512',
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '2005F771B622399C0EC7B8BBBE9B5F7989B9587175239F0695C1E5D3BFAA0CF6D747A9CEE64D78B7081CB5193541AD9D129B929653E2B68BCAE6939E281D752E',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order['ip'],
            ],
            'Card'        => [
                'Number'     => $card->getNumber(),
                'ExpireDate' => '0122',
                'CVV2'       => $card->getCvv(),
            ],
            'Order'       => [
                'OrderID'     => $order['id'],
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
            'Version'     => '512',
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '0CFE09F107274C6A07292DA061A4EECAB0F5F0CF87F831F2D3626A3346A941126C52D1D95A3B77ADF5AC348B3D25C76BA5D8D98A29557D087D3367BFFACCD25C',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order['ip'],
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
            'Version'     => '512',
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '35E8410A78E24949D78F5E025B5E05AF470B01385A2ECBFEE6C5B3CDACFF78011D387ECAFDCE4B8453D80D35C2F344F3DAA6F2EF9143079F64DE88401EC5E4F5',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order['ip'],
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

    public static function refundOrderDataProvider(): \Generator
    {
        $order = [
            'id'          => '2020110828BC',
            'ip'          => '127.15.15.1',
            'currency'    => PosInterface::CURRENCY_TRY,
            'amount'      => 123.1,
            'ref_ret_num' => '831803579226',
            'installment' => 0,
        ];
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
        yield [
            'account'      => $account,
            'order'        => $order,
            'expectedData' => [
                'Mode'        => 'TEST',
                'Version'     => '512',
                'Terminal'    => [
                    'ProvUserID' => 'PROVRFN',
                    'UserID'     => 'PROVRFN',
                    'HashData'   => 'CF49751B3B793B9E1946A08815451989D0231D68A5B495C6EABA9C400442F2E6B7DF97446CE2D3562780767E634A6ECBAA1DF69F6DF7F447884A71BDE38D12AA',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Customer'    => [
                    'IPAddress' => '127.15.15.1',
                ],
                'Order'       => [
                    'OrderID' => '2020110828BC',
                ],
                'Transaction' => [
                    'Type'                  => 'refund',
                    'InstallmentCnt'        => '',
                    'Amount'                => '12310',
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                    'OriginalRetrefNum'     => '831803579226',
                ],
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
            'Version'     => '512',
            'Terminal'    => [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '817BA6A5013BD1E75E1C2FE82AA0F2EFEF89033C31563575701BD05F3C20ADC5DD2AF65D9EF8CF81784E9DA787603E0C1321C6909BE920504BEB3A85992440F5',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ],
            'Customer'    => [
                'IPAddress'    => $order['ip'],
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

    public static function create3DPaymentRequestDataDataProvider(): \Generator
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
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'ip'          => '156.155.154.153',
        ];

        $responseData = [
            'orderid'              => '2020110828BC',
            'md'                   => '1',
            'xid'                  => '100000005xid',
            'eci'                  => '100000005eci',
            'cavv'                 => 'cavv',
            'txncurrencycode'      => '949',
            'txnamount'            => '100.25',
            'txntype'              => 'sales',
            'customeripaddress'    => '127.0.0.1',
        ];

        yield [
            'account'      => $account,
            'order'        => $order,
            'responseData' => $responseData,
            'expected'     => [
                'Mode'        => 'TEST',
                'Version'     => '512',
                'Terminal'    => [
                    'ProvUserID' => $account->getUsername(),
                    'UserID'     => $account->getUsername(),
                    'HashData'   => 'C7806BCDC5874CD227C4B7278302077F6B3E463A9C74497F263E8AF08844DEF364F4D7A089084A705D4210B9B36841E0919B72F804729F466BF00472C53AFD8B',
                    'ID'         => $account->getTerminalId(),
                    'MerchantID' => $account->getClientId(),
                ],
                'Customer'    => [
                    'IPAddress'    => '127.0.0.1',
                ],
                'Order'       => [
                    'OrderID' => '2020110828BC',
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
        ];

        $order['recurring']   = [
            'frequency'     => 2,
            'frequencyType' => 'MONTH',
            'installment'   => 3,
            'startDate'     => new \DateTimeImmutable('2023-10-15'),
        ];
        $order['installment'] = 0;

        yield 'recurring_order' => [
            'account'      => $account,
            'order'        => $order,
            'responseData' => $responseData,
            'expected'     => [
                'Mode'        => 'TEST',
                'Version'     => '512',
                'Terminal'    => [
                    'ProvUserID' => $account->getUsername(),
                    'UserID'     => $account->getUsername(),
                    'HashData'   => 'C7806BCDC5874CD227C4B7278302077F6B3E463A9C74497F263E8AF08844DEF364F4D7A089084A705D4210B9B36841E0919B72F804729F466BF00472C53AFD8B',
                    'ID'         => $account->getTerminalId(),
                    'MerchantID' => $account->getClientId(),
                ],
                'Customer'    => [
                    'IPAddress'    => '127.0.0.1',
                ],
                'Order'       => [
                    'OrderID' => '2020110828BC',
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
                'Recurring'   => [
                    'TotalPaymentNum'   => '3',
                    'FrequencyType'     => 'M',
                    'FrequencyInterval' => '2',
                    'Type'              => 'R',
                    'StartDate'         => '20231015',
                ],
            ],
        ];
    }
}

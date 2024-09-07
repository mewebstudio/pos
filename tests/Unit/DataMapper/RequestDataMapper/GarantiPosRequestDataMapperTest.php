<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\DataMapper\RequestDataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\GarantiPosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class GarantiPosRequestDataMapperTest extends TestCase
{
    private GarantiPosAccount $account;

    private CreditCardInterface $card;

    private GarantiPosRequestDataMapper $requestDataMapper;

    private array $order;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $crypt                   = CryptFactory::createGatewayCrypt(GarantiPos::class, new NullLogger());
        $this->requestDataMapper = new GarantiPosRequestDataMapper($this->dispatcher, $crypt);
        $this->requestDataMapper->setTestMode(true);

        $this->card = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet');
    }

    /**
     * @testWith ["pay", "sales"]
     * ["pre", "preauth"]
     */
    public function testMapTxType(string $txType, string $expected): void
    {
        $actual = $this->requestDataMapper->mapTxType($txType);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["sales"]
     */
    public function testMapTxTypeException(string $txType): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->requestDataMapper->mapTxType($txType);
    }

    /**
     * @return void
     */
    public function testFormatAmount(): void
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
    public function testMapCurrency(): void
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
    public function testMapInstallment($installment, $expected): void
    {
        $class  = new \ReflectionObject($this->requestDataMapper);
        $method = $class->getMethod('mapInstallment');
        $method->setAccessible(true);
        $this->assertSame($expected, $method->invokeArgs($this->requestDataMapper, [$installment]));
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(): void
    {
        $this->order['ref_ret_num'] = '831803579226';

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $this->order);

        $expectedData = $this->getSampleNonSecurePaymentPostRequestData($this->account, $this->order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, string $txType, CreditCardInterface $card, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            $order,
            $txType,
            $card
        );

        $this->assertSame($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData(): void
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
     * @dataProvider orderHistoryRequestDataProvider
     */
    public function testCreateOrderHistoryRequestData(array $order, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);

        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider historyRequestDataProvider
     */
    public function testCreateHistoryRequestData(array $data, array $expectedData): void
    {
        $actualData = $this->requestDataMapper->createHistoryRequestData($this->account, $data);

        \ksort($expectedData);
        \ksort($actualData);

        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(GarantiPosAccount $garantiPosAccount, array $order, array $responseData, array $expected): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, '', $responseData);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testGet3DFormData(): void
    {
        $account    = $this->account;
        $gatewayURL = 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine';
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

        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with($this->callback(static fn($dispatchedEvent): bool => $dispatchedEvent instanceof Before3DFormHashCalculatedEvent
                && GarantiPos::class === $dispatchedEvent->getGatewayClass()
                && $txType === $dispatchedEvent->getTxType()
                && $paymentModel === $dispatchedEvent->getPaymentModel()
                && count($dispatchedEvent->getFormInputs()) > 3));

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $this->order,
            $paymentModel,
            $txType,
            $gatewayURL,
            $this->card
        );

        //test without card
        $this->assertEquals($form, $actual);
    }

    /**
     * @return void
     */
    public function testCreateStatusRequestData(): void
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
    public function testCreateRefundRequestData(array $order, string $txType, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    /**
     * @param GarantiPosAccount $posAccount
     * @param                   $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => '512',
            'Terminal'    => [
                'ProvUserID' => $posAccount->getRefundUsername(),
                'UserID'     => $posAccount->getRefundUsername(),
                'HashData'   => '35E8410A78E24949D78F5E025B5E05AF470B01385A2ECBFEE6C5B3CDACFF78011D387ECAFDCE4B8453D80D35C2F344F3DAA6F2EF9143079F64DE88401EC5E4F5',
                'ID'         => $posAccount->getTerminalId(),
                'MerchantID' => $posAccount->getClientId(),
            ],
            'Customer'    => [
                'IPAddress' => $order['ip'],
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

    public static function nonSecurePaymentRequestDataDataProvider(): array
    {
        $card = CreditCardFactory::create(
            '5555444433332222',
            '22',
            '01',
            '123',
            'ahmet',
        );

        return [
            'basic'     => [
                'order'    => [
                    'id'          => 'order222',
                    'amount'      => 100.25,
                    'currency'    => PosInterface::CURRENCY_TRY,
                    'installment' => 0,
                    'ip'          => '127.0.0.1',
                ],
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'card'     => $card,
                'expected' => [
                    'Mode'        => 'TEST',
                    'Version'     => '512',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'HashData'   => '2005F771B622399C0EC7B8BBBE9B5F7989B9587175239F0695C1E5D3BFAA0CF6D747A9CEE64D78B7081CB5193541AD9D129B929653E2B68BCAE6939E281D752E',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '127.0.0.1',
                    ],
                    'Card'        => [
                        'Number'     => '5555444433332222',
                        'ExpireDate' => '0122',
                        'CVV2'       => '123',
                    ],
                    'Order'       => [
                        'OrderID' => 'order222',
                    ],
                    'Transaction' => [
                        'Type'                  => 'sales',
                        'InstallmentCnt'        => '',
                        'Amount'                => 10025,
                        'CurrencyCode'          => '949',
                        'CardholderPresentCode' => '0',
                        'MotoInd'               => 'N',
                    ],
                ],
            ],
            'recurring' => [
                'order'    => [
                    'id'        => 'order222',
                    'amount'    => 100.25,
                    'currency'  => PosInterface::CURRENCY_TRY,
                    'ip'        => '127.0.0.1',
                    'recurring' => [
                        'frequency'     => 3,
                        'frequencyType' => 'MONTH',
                        'installment'   => 4,
                        'startDate'     => new \DateTimeImmutable('2024-09-09 00:00:00'),
                    ],
                ],
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'card'     => $card,
                'expected' => [
                    'Mode'        => 'TEST',
                    'Version'     => '512',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'HashData'   => '2005F771B622399C0EC7B8BBBE9B5F7989B9587175239F0695C1E5D3BFAA0CF6D747A9CEE64D78B7081CB5193541AD9D129B929653E2B68BCAE6939E281D752E',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '127.0.0.1',
                    ],
                    'Card'        => [
                        'Number'     => '5555444433332222',
                        'ExpireDate' => '0122',
                        'CVV2'       => '123',
                    ],
                    'Order'       => [
                        'OrderID' => 'order222',
                    ],
                    'Transaction' => [
                        'Type'                  => 'sales',
                        'InstallmentCnt'        => '',
                        'Amount'                => 10025,
                        'CurrencyCode'          => '949',
                        'CardholderPresentCode' => '0',
                        'MotoInd'               => 'N',
                    ],
                    'Recurring'   => [
                        'TotalPaymentNum'   => '4',
                        'FrequencyType'     => 'M',
                        'FrequencyInterval' => '3',
                        'Type'              => 'R',
                        'StartDate'         => '20240909',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $posAccount
     * @param array             $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => '512',
            'Terminal'    => [
                'ProvUserID' => $posAccount->getUsername(),
                'UserID'     => $posAccount->getUsername(),
                'HashData'   => '0CFE09F107274C6A07292DA061A4EECAB0F5F0CF87F831F2D3626A3346A941126C52D1D95A3B77ADF5AC348B3D25C76BA5D8D98A29557D087D3367BFFACCD25C',
                'ID'         => $posAccount->getTerminalId(),
                'MerchantID' => $posAccount->getClientId(),
            ],
            'Customer'    => [
                'IPAddress' => $order['ip'],
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
     * @param GarantiPosAccount $posAccount
     * @param array             $order
     *
     * @return array
     */
    private function getSampleStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'Mode'        => 'TEST',
            'Version'     => '512',
            'Terminal'    => [
                'ProvUserID' => $posAccount->getUsername(),
                'UserID'     => $posAccount->getUsername(),
                'HashData'   => '35E8410A78E24949D78F5E025B5E05AF470B01385A2ECBFEE6C5B3CDACFF78011D387ECAFDCE4B8453D80D35C2F344F3DAA6F2EF9143079F64DE88401EC5E4F5',
                'ID'         => $posAccount->getTerminalId(),
                'MerchantID' => $posAccount->getClientId(),
            ],
            'Customer'    => [
                'IPAddress' => $order['ip'],
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

        yield [
            'order'        => $order,
            'tx_type'      => PosInterface::TX_TYPE_REFUND,
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
                    'Amount'                => 12310,
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                    'OriginalRetrefNum'     => '831803579226',
                ],
            ],
        ];

        yield [
            'order'        => $order,
            'tx_type'      => PosInterface::TX_TYPE_REFUND_PARTIAL,
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
                    'Amount'                => 12310,
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                    'OriginalRetrefNum'     => '831803579226',
                ],
            ],
        ];
    }

    public static function orderHistoryRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'          => 'order222',
                    'ip'          => '156.155.154.153',
                    'installment' => 0,
                ],
                'expected' => [
                    'Mode'        => 'TEST',
                    'Version'     => '512',
                    'Terminal'    => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'HashData'   => '817BA6A5013BD1E75E1C2FE82AA0F2EFEF89033C31563575701BD05F3C20ADC5DD2AF65D9EF8CF81784E9DA787603E0C1321C6909BE920504BEB3A85992440F5',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],
                    'Customer'    => [
                        'IPAddress' => '156.155.154.153',
                    ],
                    'Order'       => [
                        'OrderID' => 'order222',
                    ],
                    'Transaction' => [
                        'Type'                  => 'orderhistoryinq',
                        'InstallmentCnt'        => '',
                        'Amount'                => 100,
                        'CurrencyCode'          => '949',
                        'CardholderPresentCode' => '0',
                        'MotoInd'               => 'N',
                    ],
                ],
            ],
        ];
    }

    public static function historyRequestDataProvider(): array
    {
        return [
            [
                'data'     => [
                    'start_date' => new \DateTime('2022-05-18 00:00:00'),
                    'end_date'   => new \DateTime('2022-05-18 23:59:59'),
                    'ip'         => '127.0.0.1',
                ],
                'expected' => [
                    'Customer' => [
                        'IPAddress' => '127.0.0.1',
                    ],

                    'Mode'  => 'TEST',
                    'Order' => [
                        'OrderID'     => null,
                        'GroupID'     => null,
                        'Description' => null,
                        'StartDate'   => '18/05/2022 00:00',
                        'EndDate'     => '18/05/2022 23:59',
                        'ListPageNum' => 1,
                    ],

                    'Terminal' => [
                        'ProvUserID' => 'PROVAUT',
                        'UserID'     => 'PROVAUT',
                        'HashData'   => '9B53A55199EBAD2F486089FD7310C4BC0C61A99FC37EF61F6BBAE67FA17E47641540B203E83C9F2E64DB64B971FE6FF604274316F6D010426D6AA91BE1D924E6',
                        'ID'         => '30691298',
                        'MerchantID' => '7000679',
                    ],

                    'Transaction' => [
                        'Type'                  => 'orderlistinq',
                        'Amount'                => 100,
                        'CurrencyCode'          => '949',
                        'CardholderPresentCode' => '0',
                        'MotoInd'               => 'N',
                    ],
                    'Version'     => '512',
                ],
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
            'orderid'           => '2020110828BC',
            'md'                => '1',
            'xid'               => '100000005xid',
            'eci'               => '100000005eci',
            'cavv'              => 'cavv',
            'txncurrencycode'   => '949',
            'txnamount'         => '100.25',
            'txntype'           => 'sales',
            'customeripaddress' => '127.0.0.1',
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
                    'IPAddress' => '127.0.0.1',
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
                    'IPAddress' => '127.0.0.1',
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

<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PayForPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\PayForPosRequestDataMapper
 */
class PayForPosRequestDataMapperTest extends TestCase
{
    private PayForAccount $account;

    private CreditCardInterface $card;

    private PayForPosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    private array $order;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayForAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            PosInterface::MODEL_3D_SECURE,
            '12345678'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'lang'        => PosInterface::LANG_TR,
        ];

        $this->crypt      = $this->createMock(CryptInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->requestDataMapper = new PayForPosRequestDataMapper($this->dispatcher, $this->crypt);
        $this->card              = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet');
    }

    /**
     * @testWith ["pay", "Auth"]
     * ["pre", "PreAuth"]
     */
    public function testMapTxType(string $txType, string $expected): void
    {
        $actual = $this->requestDataMapper->mapTxType($txType);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["PreAuth"]
     */
    public function testMapTxTypeException(string $txType): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->requestDataMapper->mapTxType($txType);
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
     * @testWith ["0", "0"]
     *           ["1", "0"]
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
        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
            'lang'        => PosInterface::LANG_TR,
        ];

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $expectedData = $this->getSampleNonSecurePaymentPostRequestData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData(): void
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $this->order, PosInterface::TX_TYPE_PAY_AUTH, $this->card);

        $expectedData = $this->getSampleNonSecurePaymentRequestData($this->account, $this->order, $this->card);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData(): void
    {
        $order = [
            'id'       => '2020110828BC',
            'currency' => PosInterface::CURRENCY_TRY,
        ];

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $expectedData = $this->getSampleCancelXMLData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider orderHistoryRequestDataProvider
     */
    public function testOrderCreateHistoryRequestData(array $order, array $expectedData): void
    {
        $actualData = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
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
     * @return void
     */
    public function testCreate3DPaymentRequestData(): void
    {
        $order        = [
            'id' => '2020110828BC',
        ];
        $responseData = ['RequestGuid' => '1000000057437884'];

        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, '', $responseData);

        $expectedData = $this->getSample3DPaymentRequestData($this->account, $order, $responseData);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(
        array  $order,
        string $gatewayURL,
        string $txType,
        string $paymentModel,
        bool   $isWithCard,
        array  $expected
    ): void
    {
        $card = $isWithCard ? $this->card : null;

        $this->crypt->expects(self::once())
            ->method('create3DHash')
            ->willReturn($expected['inputs']['Hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['inputs']['Rnd']);

        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with($this->callback(function ($dispatchedEvent) use ($txType, $paymentModel) {
                return $dispatchedEvent instanceof Before3DFormHashCalculatedEvent
                    && PayForPos::class === $dispatchedEvent->getGatewayClass()
                    && $txType === $dispatchedEvent->getTxType()
                    && $paymentModel === $dispatchedEvent->getPaymentModel()
                    && count($dispatchedEvent->getFormInputs()) > 3;
            }));

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayURL,
            $card
        );

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreateStatusRequestData(): void
    {
        $order = [
            'id' => '2020110828BC',
        ];

        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $expectedData = $this->getSampleStatusRequestData($this->account, $order);
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData(): void
    {
        $order = [
            'id'       => '2020110828BC',
            'currency' => PosInterface::CURRENCY_TRY,
            'amount'   => 10.1,
        ];

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $expectedData = $this->getSampleRefundXMLData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @param AbstractPosAccount $posAccount
     * @param array              $order
     * @param array              $responseData
     *
     * @return array
     */
    private function getSample3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, array $responseData): array
    {
        return [
            'RequestGuid' => $responseData['RequestGuid'],
            'UserCode'    => $posAccount->getUsername(),
            'UserPass'    => $posAccount->getPassword(),
            'OrderId'     => $order['id'],
            'SecureType'  => '3DModelPayment',
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     * @param array              $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'MbrId'      => '5',
            'MerchantId' => $posAccount->getClientId(),
            'UserCode'   => $posAccount->getUsername(),
            'UserPass'   => $posAccount->getPassword(),
            'OrgOrderId' => $order['id'],
            'SecureType' => 'NonSecure',
            'Lang'       => 'tr',
            'TxnType'    => 'Void',
            'Currency'   => 949,
        ];
    }

    /**
     * @param AbstractPosAccount  $posAccount
     * @param array               $order
     * @param CreditCardInterface $creditCard
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, CreditCardInterface $creditCard): array
    {
        return [
            'MbrId'            => '5',
            'MerchantId'       => $posAccount->getClientId(),
            'UserCode'         => $posAccount->getUsername(),
            'UserPass'         => $posAccount->getPassword(),
            'MOTO'             => '0',
            'OrderId'          => $order['id'],
            'SecureType'       => 'NonSecure',
            'TxnType'          => 'Auth',
            'PurchAmount'      => $order['amount'],
            'Currency'         => 949,
            'InstallmentCount' => 0,
            'Lang'             => 'tr',
            'CardHolderName'   => $creditCard->getHolderName(),
            'Pan'              => $creditCard->getNumber(),
            'Expiry'           => '0122',
            'Cvv2'             => $creditCard->getCvv(),
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     * @param array              $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'MbrId'       => '5',
            'MerchantId'  => $posAccount->getClientId(),
            'UserCode'    => $posAccount->getUsername(),
            'UserPass'    => $posAccount->getPassword(),
            'OrgOrderId'  => $order['id'],
            'SecureType'  => 'NonSecure',
            'TxnType'     => 'PostAuth',
            'PurchAmount' => $order['amount'],
            'Currency'    => 949,
            'Lang'        => 'tr',
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     * @param array              $order
     *
     * @return array
     */
    private function getSampleStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'MbrId'      => '5',
            'MerchantId' => $posAccount->getClientId(),
            'UserCode'   => $posAccount->getUsername(),
            'UserPass'   => $posAccount->getPassword(),
            'OrgOrderId' => $order['id'],
            'SecureType' => 'Inquiry',
            'Lang'       => 'tr',
            'TxnType'    => 'OrderInquiry',
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     * @param array              $order
     *
     * @return array
     */
    private function getSampleRefundXMLData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'MbrId'       => '5',
            'MerchantId'  => $posAccount->getClientId(),
            'UserCode'    => $posAccount->getUsername(),
            'UserPass'    => $posAccount->getPassword(),
            'OrgOrderId'  => $order['id'],
            'SecureType'  => 'NonSecure',
            'Lang'        => 'tr',
            'TxnType'     => 'Refund',
            'PurchAmount' => $order['amount'],
            'Currency'    => 949,
        ];
    }

    public static function orderHistoryRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id'      => '2020110828BC',
                ],
                'expected' => [
                    'MerchantId' => '085300000009704',
                    'UserCode'   => 'QNB_API_KULLANICI_3DPAY',
                    'UserPass'   => 'UcBN0',
                    'MbrId'      => '5',
                    'SecureType' => 'Report',
                    'TxnType'    => 'TxnHistory',
                    'Lang'       => 'tr',
                    'OrderId'    => '2020110828BC',
                ],
            ],
        ];
    }

    public static function historyRequestDataProvider(): array
    {
        return [
            [
                'data'    => [
                    'transaction_date' => new \DateTime('2022-05-18'),
                ],
                'expected' => [
                    'MerchantId' => '085300000009704',
                    'UserCode'   => 'QNB_API_KULLANICI_3DPAY',
                    'UserPass'   => 'UcBN0',
                    'MbrId'      => '5',
                    'SecureType' => 'Report',
                    'TxnType'    => 'TxnHistory',
                    'Lang'       => 'tr',
                    'ReqDate'    => '20220518',
                ],
            ],
        ];
    }

    public static function threeDFormDataProvider(): array
    {
        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/success.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/fail.php',
            'lang'        => PosInterface::LANG_TR,
        ];

        return [
            'without_card' => [
                'order'        => $order,
                'gatewayUrl'   => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                    'method'  => 'POST',
                    'inputs'  => [
                        'MbrId'            => '5',
                        'MerchantID'       => '085300000009704',
                        'UserCode'         => 'QNB_API_KULLANICI_3DPAY',
                        'OrderId'          => '2020110828BC',
                        'Lang'             => 'tr',
                        'SecureType'       => '3DModel',
                        'TxnType'          => 'Auth',
                        'PurchAmount'      => '100.01',
                        'InstallmentCount' => '0',
                        'Currency'         => '949',
                        'OkUrl'            => 'http://localhost/finansbank-payfor/3d/success.php',
                        'FailUrl'          => 'http://localhost/finansbank-payfor/3d/fail.php',
                        'Rnd'              => '1deda47050cd38112cbf91f4',
                        'Hash'             => 'BSj3xu8dYQbdw5YM4JvTS+vmyUI=',
                    ],
                ],
            ],
            'with_card'    => [
                'order'        => $order,
                'gatewayUrl'   => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => true,
                'expected'     => [
                    'gateway' => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                    'method'  => 'POST',
                    'inputs'  => [
                        'MbrId'            => '5',
                        'MerchantID'       => '085300000009704',
                        'UserCode'         => 'QNB_API_KULLANICI_3DPAY',
                        'OrderId'          => '2020110828BC',
                        'Lang'             => 'tr',
                        'SecureType'       => '3DModel',
                        'TxnType'          => 'Auth',
                        'PurchAmount'      => '100.01',
                        'InstallmentCount' => '0',
                        'Currency'         => '949',
                        'OkUrl'            => 'http://localhost/finansbank-payfor/3d/success.php',
                        'FailUrl'          => 'http://localhost/finansbank-payfor/3d/fail.php',
                        'Rnd'              => '1deda47050cd38112cbf91f4',
                        'Hash'             => 'BSj3xu8dYQbdw5YM4JvTS+vmyUI=',
                        'CardHolderName'   => 'ahmet',
                        'Pan'              => '5555444433332222',
                        'Expiry'           => '0122',
                        'Cvv2'             => '123',
                    ],
                ],
            ],
            '3d_host'      => [
                'order'        => $order,
                'gatewayUrl'   => 'https://vpostest.qnbfinansbank.com/Gateway/3DHost.aspx',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://vpostest.qnbfinansbank.com/Gateway/3DHost.aspx',
                    'method'  => 'POST',
                    'inputs'  => [
                        'MbrId'            => '5',
                        'MerchantID'       => '085300000009704',
                        'UserCode'         => 'QNB_API_KULLANICI_3DPAY',
                        'OrderId'          => '2020110828BC',
                        'Lang'             => 'tr',
                        'SecureType'       => '3DHost',
                        'TxnType'          => 'Auth',
                        'PurchAmount'      => '100.01',
                        'InstallmentCount' => '0',
                        'Currency'         => '949',
                        'OkUrl'            => 'http://localhost/finansbank-payfor/3d/success.php',
                        'FailUrl'          => 'http://localhost/finansbank-payfor/3d/fail.php',
                        'Rnd'              => '1deda47050cd38112cbf91f4',
                        'Hash'             => 'BSj3xu8dYQbdw5YM4JvTS+vmyUI=',
                    ],
                ],
            ],
        ];
    }
}

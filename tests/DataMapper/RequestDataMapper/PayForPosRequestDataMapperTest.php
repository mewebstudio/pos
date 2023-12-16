<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PayForPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
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

    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../../config/pos_test.php';

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

        $this->crypt = $this->createMock(CryptInterface::class);
        $dispatcher  = $this->createMock(EventDispatcherInterface::class);
        $pos         = PosFactory::createPosGateway($this->account, $this->config, $dispatcher);

        $this->requestDataMapper = new PayForPosRequestDataMapper($dispatcher, $this->crypt);
        $this->card              = CreditCardFactory::create($pos, '5555444433332222', '22', '01', '123', 'ahmet');
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
     * @testWith ["0", "0"]
     *           ["1", "0"]
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
            'id'       => '2020110828BC',
            'currency' => PosInterface::CURRENCY_TRY,
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
        $order = [
            'orderId' => '2020110828BC',
            'reqDate' => '20220518',
        ];

        $actual = $this->requestDataMapper->createHistoryRequestData($this->account, [], $order);

        $expectedData = $this->getSampleHistoryRequestData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DPaymentRequestData()
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
    public function testCreateStatusRequestData()
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
    public function testCreateRefundRequestData()
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
     * @param AbstractPosAccount $account
     * @param array              $order
     * @param array              $responseData
     *
     * @return array
     */
    private function getSample3DPaymentRequestData(AbstractPosAccount $account, array $order, array $responseData): array
    {
        return [
            'RequestGuid' => $responseData['RequestGuid'],
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'OrderId'     => $order['id'],
            'SecureType'  => '3DModelPayment',
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $account, array $order): array
    {
        return [
            'MbrId'      => '5',
            'MerchantId' => $account->getClientId(),
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'OrgOrderId' => $order['id'],
            'SecureType' => 'NonSecure',
            'Lang'       => 'tr',
            'TxnType'    => 'Void',
            'Currency'   => 949,
        ];
    }

    /**
     * @param AbstractPosAccount  $account
     * @param array               $order
     * @param CreditCardInterface $card
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, CreditCardInterface $card): array
    {
        return [
            'MbrId'            => '5',
            'MerchantId'       => $account->getClientId(),
            'UserCode'         => $account->getUsername(),
            'UserPass'         => $account->getPassword(),
            'MOTO'             => '0',
            'OrderId'          => $order['id'],
            'SecureType'       => 'NonSecure',
            'TxnType'          => 'Auth',
            'PurchAmount'      => $order['amount'],
            'Currency'         => 949,
            'InstallmentCount' => 0,
            'Lang'             => 'tr',
            'CardHolderName'   => $card->getHolderName(),
            'Pan'              => $card->getNumber(),
            'Expiry'           => '0122',
            'Cvv2'             => $card->getCvv(),
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $account, array $order): array
    {
        return [
            'MbrId'       => '5',
            'MerchantId'  => $account->getClientId(),
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'OrgOrderId'  => $order['id'],
            'SecureType'  => 'NonSecure',
            'TxnType'     => 'PostAuth',
            'PurchAmount' => $order['amount'],
            'Currency'    => 949,
            'Lang'        => 'tr',
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleStatusRequestData(AbstractPosAccount $account, array $order): array
    {
        return [
            'MbrId'      => '5',
            'MerchantId' => $account->getClientId(),
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'OrgOrderId' => $order['id'],
            'SecureType' => 'Inquiry',
            'Lang'       => 'tr',
            'TxnType'    => 'OrderInquiry',
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $order
     *
     * @return array
     */
    private function getSampleRefundXMLData(AbstractPosAccount $account, array $order): array
    {
        return [
            'MbrId'       => '5',
            'MerchantId'  => $account->getClientId(),
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'OrgOrderId'  => $order['id'],
            'SecureType'  => 'NonSecure',
            'Lang'        => 'tr',
            'TxnType'     => 'Refund',
            'PurchAmount' => $order['amount'],
            'Currency'    => 949,
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param array              $customQueryData
     *
     * @return array
     */
    private function getSampleHistoryRequestData(AbstractPosAccount $account, array $customQueryData): array
    {
        $requestData = [
            'MbrId'      => '5',
            'MerchantId' => $account->getClientId(),
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'SecureType' => 'Report',
            'TxnType'    => 'TxnHistory',
            'Lang'       => 'tr',
        ];

        if (isset($customQueryData['orderId'])) {
            $requestData['OrderId'] = $customQueryData['orderId'];
        } elseif (isset($customQueryData['reqDate'])) {
            //ReqData YYYYMMDD format
            $requestData['ReqDate'] = $customQueryData['reqDate'];
        }

        return $requestData;
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
                'txType'       => PosInterface::TX_PAY,
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
                'txType'       => PosInterface::TX_PAY,
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
            '3d_host' => [
                'order'        => $order,
                'gatewayUrl'   => 'https://vpostest.qnbfinansbank.com/Gateway/3DHost.aspx',
                'txType'       => PosInterface::TX_PAY,
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

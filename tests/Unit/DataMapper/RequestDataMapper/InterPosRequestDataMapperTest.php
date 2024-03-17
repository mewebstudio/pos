<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\InterPosRequestDataMapper;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\InterPosRequestDataMapper
 */
class InterPosRequestDataMapperTest extends TestCase
{
    private InterPosAccount $account;

    private CreditCardInterface $card;

    private InterPosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    private array $order;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../../config/pos_test.php';

        $userCode     = 'InterTestApi';
        $userPass     = '3';
        $shopCode     = '3123';
        $merchantPass = 'gDg1N';

        $this->account = AccountFactory::createInterPosAccount(
            'denizbank',
            $shopCode,
            $userCode,
            $userPass,
            PosInterface::MODEL_3D_SECURE,
            $merchantPass
        );

        $this->order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos        = PosFactory::createPosGateway($this->account, $config, $dispatcher);

        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new InterPosRequestDataMapper($dispatcher, $this->crypt);

        $this->card = CreditCardFactory::create($pos, '5555444433332222', '21', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
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
        $order = [
            'id'       => '2020110828BC',
            'amount'   => 320,
            'currency' => PosInterface::CURRENCY_TRY,
        ];

        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $expectedData = $this->getSampleNonSecurePaymentPostRequestData($order, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePaymentRequestData(): void
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $this->order, PosInterface::TX_TYPE_PAY_AUTH, $this->card);

        $expectedData = $this->getSampleNonSecurePaymentRequestData($this->order, $this->card, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateCancelRequestData(): void
    {
        $order = [
            'id'   => '2020110828BC',
            'lang' => PosInterface::LANG_EN,
        ];

        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $expectedData = $this->getSampleCancelXMLData($order, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DPaymentRequestData(): void
    {
        $order        = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'lang'        => PosInterface::LANG_EN,
        ];
        $responseData = [
            'MD'                      => '1',
            'PayerTxnId'              => '2',
            'Eci'                     => '3',
            'PayerAuthenticationCode' => '4',
        ];

        $actual = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, PosInterface::TX_TYPE_PAY_AUTH, $responseData);

        $expectedData = $this->getSample3DPaymentRequestData($order, $this->account, $responseData);
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
    public function testCreateStatusRequestData(): void
    {
        $order = [
            'id'   => '2020110828BC',
            'lang' => PosInterface::LANG_EN,
        ];

        $actual = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $expectedData = $this->getSampleStatusRequestData($order, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData(): void
    {
        $order = [
            'id'     => '2020110828BC',
            'amount' => 50,
        ];

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $expectedData = $this->getSampleRefundXMLData($order, $this->account);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @param array           $order
     * @param InterPosAccount $interPosAccount
     * @param array           $responseData
     *
     * @return array
     */
    private function getSample3DPaymentRequestData(array $order, InterPosAccount $interPosAccount, array $responseData): array
    {
        return [
            'UserCode'                => $interPosAccount->getUsername(),
            'UserPass'                => $interPosAccount->getPassword(),
            'ShopCode'                => $interPosAccount->getClientId(),
            'TxnType'                 => 'Auth',
            'SecureType'              => 'NonSecure',
            'OrderId'                 => $order['id'],
            'PurchAmount'             => $order['amount'],
            'Currency'                => '949',
            'InstallmentCount'        => '',
            'MD'                      => $responseData['MD'],
            'PayerTxnId'              => $responseData['PayerTxnId'],
            'Eci'                     => $responseData['Eci'],
            'PayerAuthenticationCode' => $responseData['PayerAuthenticationCode'],
            'MOTO'                    => '0',
            'Lang'                    => $order['lang'],
        ];
    }

    /**
     * @param array           $order
     * @param InterPosAccount $interPosAccount
     *
     * @return array
     */
    private function getSampleCancelXMLData(array $order, InterPosAccount $interPosAccount): array
    {
        return [
            'UserCode'   => $interPosAccount->getUsername(),
            'UserPass'   => $interPosAccount->getPassword(),
            'ShopCode'   => $interPosAccount->getClientId(),
            'OrderId'    => null,
            'orgOrderId' => $order['id'],
            'TxnType'    => 'Void',
            'SecureType' => 'NonSecure',
            'Lang'       => $order['lang'],
        ];
    }

    /**
     * @param array               $order
     * @param CreditCardInterface $creditCard
     * @param InterPosAccount     $interPosAccount
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(array $order, CreditCardInterface $creditCard, InterPosAccount $interPosAccount): array
    {
        $requestData = [
            'UserCode'         => $interPosAccount->getUsername(),
            'UserPass'         => $interPosAccount->getPassword(),
            'ShopCode'         => $interPosAccount->getClientId(),
            'TxnType'          => 'Auth',
            'SecureType'       => 'NonSecure',
            'OrderId'          => $order['id'],
            'PurchAmount'      => $order['amount'],
            'Currency'         => '949',
            'InstallmentCount' => '',
            'MOTO'             => '0',
            'Lang'             => $order['lang'],
        ];

        $requestData['CardType'] = '0';
        $requestData['Pan']      = $creditCard->getNumber();
        $requestData['Expiry']   = '1221';
        $requestData['Cvv2']     = $creditCard->getCvv();

        return $requestData;
    }

    /**
     * @param array           $order
     * @param InterPosAccount $interPosAccount
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(array $order, InterPosAccount $interPosAccount): array
    {
        return [
            'UserCode'    => $interPosAccount->getUsername(),
            'UserPass'    => $interPosAccount->getPassword(),
            'ShopCode'    => $interPosAccount->getClientId(),
            'TxnType'     => 'PostAuth',
            'SecureType'  => 'NonSecure',
            'OrderId'     => null,
            'orgOrderId'  => $order['id'],
            'PurchAmount' => $order['amount'],
            'Currency'    => '949',
            'MOTO'        => '0',
        ];
    }

    /**
     * @param array           $order
     * @param InterPosAccount $interPosAccount
     *
     * @return array
     */
    private function getSampleStatusRequestData(array $order, InterPosAccount $interPosAccount): array
    {
        return [
            'UserCode'   => $interPosAccount->getUsername(),
            'UserPass'   => $interPosAccount->getPassword(),
            'ShopCode'   => $interPosAccount->getClientId(),
            'OrderId'    => null,
            'orgOrderId' => $order['id'],
            'TxnType'    => 'StatusHistory',
            'SecureType' => 'NonSecure',
            'Lang'       => $order['lang'],
        ];
    }

    /**
     * @param array           $order
     * @param InterPosAccount $interPosAccount
     *
     * @return array
     */
    private function getSampleRefundXMLData(array $order, InterPosAccount $interPosAccount): array
    {
        return [
            'UserCode'    => $interPosAccount->getUsername(),
            'UserPass'    => $interPosAccount->getPassword(),
            'ShopCode'    => $interPosAccount->getClientId(),
            'OrderId'     => null,
            'orgOrderId'  => $order['id'],
            'PurchAmount' => $order['amount'],
            'TxnType'     => 'Refund',
            'SecureType'  => 'NonSecure',
            'Lang'        => $interPosAccount->getLang(),
            'MOTO'        => '0',
        ];
    }

    public static function threeDFormDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];

        return [
            'without_card' => [
                'order'        => $order,
                'gatewayUrl'   => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                    'method'  => 'POST',
                    'inputs'  => [
                        'ShopCode'         => '3123',
                        'TxnType'          => 'Auth',
                        'SecureType'       => '3DModel',
                        'PurchAmount'      => '100.25',
                        'OrderId'          => 'order222',
                        'OkUrl'            => 'https://domain.com/success',
                        'FailUrl'          => 'https://domain.com/fail_url',
                        'Rnd'              => 'rand-12',
                        'Lang'             => 'tr',
                        'Currency'         => '949',
                        'InstallmentCount' => '',
                        'Hash'             => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
                    ],
                ],
            ],
            'with_card'    => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => true,
                'expected'     => [
                    'gateway' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'ShopCode'         => '3123',
                        'TxnType'          => 'Auth',
                        'SecureType'       => '3DModel',
                        'PurchAmount'      => '100.25',
                        'OrderId'          => 'order222',
                        'OkUrl'            => 'https://domain.com/success',
                        'FailUrl'          => 'https://domain.com/fail_url',
                        'Rnd'              => 'rand-12',
                        'Lang'             => 'tr',
                        'Currency'         => '949',
                        'InstallmentCount' => '',
                        'Hash'             => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
                        'CardType'         => '0',
                        'Pan'              => '5555444433332222',
                        'Expiry'           => '1221',
                        'Cvv2'             => '122',
                    ],
                ],
            ],
            '3d_host'      => [
                'order'        => $order,
                'gatewayUrl'   => 'https://test.inter-vpos.com.tr/mpi/3DHost.aspx',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://test.inter-vpos.com.tr/mpi/3DHost.aspx',
                    'method'  => 'POST',
                    'inputs'  => [
                        'ShopCode'         => '3123',
                        'TxnType'          => 'Auth',
                        'SecureType'       => '3DHost',
                        'PurchAmount'      => '100.25',
                        'OrderId'          => 'order222',
                        'OkUrl'            => 'https://domain.com/success',
                        'FailUrl'          => 'https://domain.com/fail_url',
                        'Rnd'              => 'rand-12',
                        'Lang'             => 'tr',
                        'Currency'         => '949',
                        'InstallmentCount' => '',
                        'Hash'             => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
                    ],
                ],
            ],
        ];
    }
}

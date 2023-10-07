<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper\RequestDataMapper;

use Exception;
use InvalidArgumentException;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * PosNetRequestDataMapperTest
 */
class PosNetRequestDataMapperTest extends TestCase
{
    /** @var AbstractCreditCard */
    private $card;

    /** @var PosNetRequestDataMapper */
    private $requestDataMapper;

    private $order;

    /** @var PosNetAccount */
    private $account;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../config/pos_test.php';

        $this->account = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706598320',
            '67005551',
            '27426',
            PosInterface::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $this->order = [
            'id'          => 'TST_190620093100_024',
            'amount'      => '1.75',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'rand'        => '0.43625700 1604831630',
            'lang'        => PosInterface::LANG_TR,
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos = PosFactory::createPosGateway($this->account, $config, $dispatcher);
        $crypt = PosFactory::getGatewayCrypt(PosNet::class, new NullLogger());
        $this->requestDataMapper = new PosNetRequestDataMapper($dispatcher, $crypt);
        $this->card              = CreditCardFactory::create($pos, '5555444433332222', '22', '01', '123', 'ahmet');
    }

    /**
     * @return void
     */
    public function testMapCurrency()
    {
        $this->assertEquals('TL', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertEquals('EU', $this->requestDataMapper->mapCurrency(PosInterface::CURRENCY_EUR));
    }

    /**
     * @return void
     */
    public function testAmountFormat()
    {
        $this->assertSame(100000, PosNetRequestDataMapper::amountFormat(1000));
        $this->assertSame(100000, PosNetRequestDataMapper::amountFormat(1000.00));
        $this->assertSame(100001, PosNetRequestDataMapper::amountFormat(1000.01));
    }

    /**
     * @param string|int|null $installment
     * @param string|int      $expected
     *
     * @testWith ["0", "00"]
     *           ["1", "00"]
     *           ["2", "02"]
     *           ["12", "12"]
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
    public function testMapOrderIdToPrefixedOrderId()
    {
        $this->assertSame('TDSC00000000000000000010', PosNetRequestDataMapper::mapOrderIdToPrefixedOrderId(10, PosInterface::MODEL_3D_SECURE));
        $this->assertSame('000000000000000000000010', PosNetRequestDataMapper::mapOrderIdToPrefixedOrderId(10, PosInterface::MODEL_3D_PAY));
        $this->assertSame('000000000000000000000010', PosNetRequestDataMapper::mapOrderIdToPrefixedOrderId(10, PosInterface::MODEL_NON_SECURE));
    }

    /**
     * @return void
     */
    public function testFormatOrderId()
    {
        $this->assertSame('0010', PosNetRequestDataMapper::formatOrderId(10, 4));
        $this->assertSame('12345', PosNetRequestDataMapper::formatOrderId(12345, 5));
        $this->assertSame('123456789012345566fm', PosNetRequestDataMapper::formatOrderId('123456789012345566fm'));
    }

    /**
     * @return void
     */
    public function testFormatOrderIdFail()
    {
        $this->expectException(InvalidArgumentException::class);
        PosNetRequestDataMapper::formatOrderId('123456789012345566fml');
    }

    /**
     * @return void
     */
    public function testCreateNonSecurePostAuthPaymentRequestData()
    {
        $order = [
            'id'           => '2020110828BC',
            'ref_ret_num' => '019676067890000191',
            'amount'       => 10.02,
            'currency'     => PosInterface::CURRENCY_TRY,
            'installment'  => '2',
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
            'id' => '2020110828BC',
            'payment_model' => PosInterface::MODEL_3D_SECURE,
        ];

        $actual       = $this->requestDataMapper->createCancelRequestData($this->account, $order);
        $expectedData = $this->getSampleCancelXMLData($this->account, $order);
        $this->assertEquals($expectedData, $actual);

        $order = [
            'ref_ret_num' => '2020110828BCNUM',
        ];

        $actual       = $this->requestDataMapper->createCancelRequestData($this->account, $order);
        $expectedData = $this->getSampleCancelXMLData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @dataProvider create3DPaymentRequestDataDataProvider
     *
     * @return void
     */
    public function testCreate3DPaymentRequestData(PosNetAccount $account, array $order, string $txType, array $responseData, array $expected)
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($account, $order, $txType, $responseData);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DEnrollmentCheckRequestData()
    {
        $expected = $this->getSample3DEnrollmentCheckRequestData($this->account, $this->order, $this->card);
        $actual   = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $this->order, PosInterface::TX_PAY, $this->card);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @return void
     */
    public function testCreate3DEnrollmentCheckRequestDataFailTooLongOrderId()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->order['id'] = 'd32458293945098y439244343';
        $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $this->order, PosInterface::TX_PAY, $this->card);
    }

    /**
     * @return void
     */
    public function testCreate3DResolveMerchantRequestData()
    {
        $order        = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
        ];
        $responseData = [
            'BankPacket'     => 'F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
            'MerchantPacket' => 'E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
            'Sign'           => '9998F61E1D0C0FB6EC5203A748124F30',
        ];

        $actualData   = $this->requestDataMapper->create3DResolveMerchantRequestData($this->account, $order, $responseData);
        $expectedData = $this->getSampleResolveMerchantDataXMLData($this->account, $responseData);
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testCreate3DFormData()
    {
        $gatewayURL      = 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService';
        $ooTxSuccessData = $this->getSample3DEnrollmentCheckResponseData();

        $expected = $this->requestDataMapper->create3DFormData(
            $this->account,
            $this->order,
            PosInterface::MODEL_3D_SECURE,
            '',
            $gatewayURL,
            null,
            $ooTxSuccessData['oosRequestDataResponse']
        );

        $actual   = $this->getSample3DFormData($this->account, $this->order, $ooTxSuccessData['oosRequestDataResponse'], $gatewayURL);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreateStatusRequestData()
    {
        $order = [
            'id'            => '2020110828BC',
            'payment_model' => PosInterface::MODEL_3D_SECURE,
        ];

        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $expectedData = $this->getSampleStatusRequestData($this->account);
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData()
    {
        $order = [
            'id'            => '2020110828BC',
            'payment_model' => PosInterface::MODEL_3D_SECURE,
            'amount'        => 50,
            'currency'      => PosInterface::CURRENCY_TRY,
        ];

        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $expectedData = $this->getSampleRefundXMLData($this->account, $order);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @param PosNetAccount $account
     * @param array         $order
     * @param               $oosTxResponseData
     * @param string        $gatewayURL
     *
     * @return array
     */
    private function getSample3DFormData(AbstractPosAccount $account, array $order, $oosTxResponseData, string $gatewayURL): array
    {
        $inputs = [
            'posnetData'        => $oosTxResponseData['data1'],
            'posnetData2'       => $oosTxResponseData['data2'],
            'mid'               => $account->getClientId(),
            'posnetID'          => $account->getPosNetId(),
            'digest'            => $oosTxResponseData['sign'],
            'merchantReturnURL' => $order['success_url'],
            'url'               => '',
            'lang'              => 'tr',
        ];

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * @return array
     */
    private function getSample3DEnrollmentCheckResponseData(): array
    {
        return [
            'approved'               => '1', //1:Başarılı
            'respCode'               => '',
            'respText'               => '',
            'oosRequestDataResponse' => [
                'data1' => 'AEFE78BFC852867FF57078B723E284D1BD52EED8264C6CBD110A1A9EA5EAA7533D1A82EFD614032D686C507738FDCDD2EDD00B22DEFEFE0795DC4674C16C02EBBFEC9DF0F495D5E23BE487A798BF8293C7C1D517D9600C96CBFD8816C9D8F8257442906CB9B10D8F1AABFBBD24AA6FB0E5533CDE67B0D9EA5ED621B91BF6991D5362182302B781241B56E47BAE1E86BC3D5AE7606212126A4E97AFC2',
                'data2' => '69D04861340091B7014B15158CA3C83413031B406F08B3792A0114C9958E6F0F216966C5EE32EAEEC7158BFF59DFCB77E20CD625',
                'sign'  => '9998F61E1D0C0FB6EC5203A748124F30',
            ],
        ];
    }

    public static function create3DPaymentRequestDataDataProvider()
    {
        $account = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706598320',
            '67005551',
            '27426',
            PosInterface::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );
        return [
            'test1' => [
                'account' => $account,
                'order' => [
                    'id'          => '2020110828BC',
                    'amount'      => 100.01,
                    'installment' => '0',
                    'currency'    => PosInterface::CURRENCY_TRY,
                ],
                'txType' => PosInterface::TX_PAY,
                'responseData' => [
                    'BankPacket'     => 'F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                    'MerchantPacket' => 'E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                    'Sign'           => '9998F61E1D0C0FB6EC5203A748124F30',
                ],
                'expected' => [
                    'mid'         => $account->getClientId(),
                    'tid'         => $account->getTerminalId(),
                    'oosTranData' => [
                        'bankData'     => 'F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                        'merchantData' => 'E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
                        'sign'         => '9998F61E1D0C0FB6EC5203A748124F30',
                        'wpAmount'     => 0,
                        'mac'          => 'oE7zwV87uOc2DFpGPlr4jQRQ0z9LsxGw56c7vaiZkTo=',
                    ],
                ]
            ]
        ];
    }

    /**
     * @param PosNetAccount $account
     * @param array         $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $account, array $order): array
    {
        $requestData = [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            'reverse'          => [
                'transaction' => 'sale',
            ],
        ];

        //either will work
        if (isset($order['ref_ret_num'])) {
            $requestData['reverse']['hostLogKey'] = $order['ref_ret_num'];
        } else {
            $requestData['reverse']['orderID'] = 'TDSC000000002020110828BC';
        }

        return $requestData;
    }

    /**
     * @param PosNetAccount      $account
     * @param array              $order
     * @param AbstractCreditCard $card
     *
     * @return array
     */
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, AbstractCreditCard $card): array
    {
        return [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            'sale'             => [
                'orderID'      => $order['id'],
                'installment'  => $order['installment'],
                'amount'       => 175,
                'currencyCode' => 'TL',
                'ccno'         => $card->getNumber(),
                'expDate'      => '2201',
                'cvc'          => $card->getCvv(),
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     * @param array         $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $account, array $order): array
    {
        return [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            'capt'             => [
                'hostLogKey'   => $order['ref_ret_num'],
                'amount'       => 1002,
                'currencyCode' => 'TL',
                'installment'  => '02',
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     *
     * @return array
     */
    private function getSampleStatusRequestData(AbstractPosAccount $account): array
    {
        return [
            'mid'       => $account->getClientId(),
            'tid'       => $account->getTerminalId(),
            'agreement' => [
                'orderID' => 'TDSC000000002020110828BC',
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     * @param               $order
     *
     * @return array
     */
    private function getSampleRefundXMLData(AbstractPosAccount $account, array $order): array
    {
        $requestData = [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            'return'           => [
                'amount'       => 5000,
                'currencyCode' => 'TL',
            ],
        ];

        if (isset($order['ref_ret_num'])) {
            $requestData['return']['hostLogKey'] = $order['ref_ret_num'];
        } else {
            $requestData['return']['orderID'] = 'TDSC000000002020110828BC';
        }

        return $requestData;
    }

    /**
     * @param PosNetAccount      $account
     * @param                    $order
     * @param AbstractCreditCard $card
     *
     * @return array
     */
    private function getSample3DEnrollmentCheckRequestData(PosNetAccount $account, array $order, AbstractCreditCard $card): array
    {
        return [
            'mid'            => $account->getClientId(),
            'tid'            => $account->getTerminalId(),
            'oosRequestData' => [
                'posnetid'       => $account->getPosNetId(),
                'ccno'           => $card->getNumber(),
                'expDate'        => '2201',
                'cvc'            => $this->card->getCvv(),
                'amount'         => 175,
                'currencyCode'   => 'TL',
                'installment'    => $order['installment'],
                'XID'            => $this->requestDataMapper::formatOrderId($order['id']),
                'cardHolderName' => $card->getHolderName(),
                'tranType'       => 'Sale',
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     * @param array         $responseData
     *
     * @return array
     */
    private function getSampleResolveMerchantDataXMLData(AbstractPosAccount $account, array $responseData): array
    {
        return [
            'mid'                    => $account->getClientId(),
            'tid'                    => $account->getTerminalId(),
            'oosResolveMerchantData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
                'mac'          => 'oE7zwV87uOc2DFpGPlr4jQRQ0z9LsxGw56c7vaiZkTo=',
            ],
        ];
    }
}

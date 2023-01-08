<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\DataMapper;

use Exception;
use InvalidArgumentException;
use Mews\Pos\DataMapper\PosNetRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\PosNet;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * PosNetRequestDataMapperTest
 */
class PosNetRequestDataMapperTest extends TestCase
{
    /** @var PosNet */
    private $pos;

    /** @var AbstractCreditCard */
    private $card;

    /** @var PosNetRequestDataMapper */
    private $requestDataMapper;

    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $threeDAccount = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706598320',
            'XXXXXX',
            'XXXXXX',
            '67005551',
            '27426',
            AbstractGateway::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $this->order = [
            'id'          => 'TST_190620093100_024',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => '1.75',
            'installment' => 0,
            'currency'    => 'TL',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'rand'        => '0.43625700 1604831630',
            'lang'        => AbstractGateway::LANG_TR,
        ];

        $this->pos = PosFactory::createPosGateway($threeDAccount);
        $this->pos->setTestMode(true);
        $crypt = PosFactory::getGatewayCrypt(PosNet::class, new NullLogger());
        $this->requestDataMapper = new PosNetRequestDataMapper($crypt);
        $this->card              = CreditCardFactory::create($this->pos, '5555444433332222', '22', '01', '123', 'ahmet');
    }

    /**
     * @return void
     */
    public function testMapCurrency()
    {
        $this->assertEquals('TL', $this->requestDataMapper->mapCurrency('TRY'));
        $this->assertEquals('EU', $this->requestDataMapper->mapCurrency('EUR'));
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
        $this->assertSame('TDSC00000000000000000010', PosNetRequestDataMapper::mapOrderIdToPrefixedOrderId(10, AbstractGateway::MODEL_3D_SECURE));
        $this->assertSame('000000000000000000000010', PosNetRequestDataMapper::mapOrderIdToPrefixedOrderId(10, AbstractGateway::MODEL_3D_PAY));
        $this->assertSame('000000000000000000000010', PosNetRequestDataMapper::mapOrderIdToPrefixedOrderId(10, AbstractGateway::MODEL_NON_SECURE));
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
            'currency'     => 'TRY',
            'installment'  => '2',
        ];

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
        $card  = CreditCardFactory::create($pos, '5555444433332222', '22', '01', '123', 'ahmet');
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
        $pos   = $this->pos;
        $order = [
            'id' => '2020110828BC',
        ];
        $pos->prepare($order, AbstractGateway::TX_CANCEL);
        $actual       = $this->requestDataMapper->createCancelRequestData($pos->getAccount(), $pos->getOrder());
        $expectedData = $this->getSampleCancelXMLData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);

        $order = [
            'ref_ret_num' => '2020110828BCNUM',
        ];
        $pos->prepare($order, AbstractGateway::TX_CANCEL);
        $actual       = $this->requestDataMapper->createCancelRequestData($pos->getAccount(), $pos->getOrder());
        $expectedData = $this->getSampleCancelXMLData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DPaymentRequestData()
    {
        $order        = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
        ];
        $responseData = [
            'BankPacket'     => 'F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
            'MerchantPacket' => 'E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
            'Sign'           => '9998F61E1D0C0FB6EC5203A748124F30',
        ];

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actual = $this->requestDataMapper->create3DPaymentRequestData($pos->getAccount(), $pos->getOrder(), AbstractGateway::TX_PAY, $responseData);

        $expectedData = $this->getSample3DPaymentRequestData($pos->getAccount(), $responseData);
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DEnrollmentCheckRequestData()
    {
        $pos = $this->pos;
        $pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $expected = $this->getSample3DEnrollmentCheckRequestData($pos->getAccount(), $pos->getOrder(), $pos->getCard());
        $actual   = $this->requestDataMapper->create3DEnrollmentCheckRequestData($pos->getAccount(), $pos->getOrder(), AbstractGateway::TX_PAY, $pos->getCard());
        $this->assertEquals($expected, $actual);
    }


    /**
     * @return void
     */
    public function testCreate3DEnrollmentCheckRequestDataFailTooLongOrderId()
    {
        $this->expectException(InvalidArgumentException::class);
        $pos = $this->pos;
        $order = $this->order;
        $order['id'] = 'd32458293945098y439244343';
        $pos->prepare($order, AbstractGateway::TX_PAY, $this->card);
        $this->requestDataMapper->create3DEnrollmentCheckRequestData($pos->getAccount(), $pos->getOrder(), AbstractGateway::TX_PAY, $pos->getCard());
    }

    /**
     * @return void
     */
    public function testCreate3DResolveMerchantRequestData()
    {
        $pos          = $this->pos;
        $order        = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
        ];
        $responseData = [
            'BankPacket'     => 'F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
            'MerchantPacket' => 'E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
            'Sign'           => '9998F61E1D0C0FB6EC5203A748124F30',
        ];

        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actualData   = $this->requestDataMapper->create3DResolveMerchantRequestData($pos->getAccount(), $pos->getOrder(), $responseData);
        $expectedData = $this->getSampleResolveMerchantDataXMLData($pos->getAccount(), $responseData);
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
        $pos             = $this->pos;
        $pos->prepare($this->order, AbstractGateway::TX_PAY);

        $expected = $this->requestDataMapper->create3DFormData(
            $pos->getAccount(),
            $pos->getOrder(),
            '',
            $gatewayURL,
            null,
            $ooTxSuccessData['oosRequestDataResponse']
        );
        $actual   = $this->getSample3DFormData($pos->getAccount(), $pos->getOrder(), $ooTxSuccessData['oosRequestDataResponse'], $gatewayURL);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreateStatusRequestData()
    {
        $order = [
            'id'   => '2020110828BC',
            'type' => 'status',
        ];


        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_STATUS);

        $actualData = $this->requestDataMapper->createStatusRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleStatusRequestData($pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
    }

    /**
     * @return void
     */
    public function testCreateRefundRequestData()
    {
        $order = [
            'id'       => '2020110828BC',
            'amount'   => 50,
            'currency' => 'TRY',
        ];

        $pos = $this->pos;
        $pos->prepare($order, AbstractGateway::TX_REFUND);

        $actual = $this->requestDataMapper->createRefundRequestData($pos->getAccount(), $pos->getOrder());

        $expectedData = $this->getSampleRefundXMLData($pos->getAccount(), $pos->getOrder());
        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @param PosNetAccount $account
     * @param               $order
     * @param               $oosTxResponseData
     * @param string        $gatewayURL
     *
     * @return array
     */
    private function getSample3DFormData(AbstractPosAccount $account, $order, $oosTxResponseData, string $gatewayURL): array
    {
        $inputs = [
            'posnetData'        => $oosTxResponseData['data1'],
            'posnetData2'       => $oosTxResponseData['data2'],
            'mid'               => $account->getClientId(),
            'posnetID'          => $account->getPosNetId(),
            'digest'            => $oosTxResponseData['sign'],
            'vftCode'           => $account->promotion_code ?? null,
            'merchantReturnURL' => $order->success_url,
            'url'               => '',
            'lang'              => 'tr',
        ];

        return [
            'gateway' => $gatewayURL,
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

    /**
     * @param PosNetAccount $account
     * @param array         $responseData
     *
     * @return array
     */
    private function getSample3DPaymentRequestData(AbstractPosAccount $account, array $responseData): array
    {
        return [
            'mid'         => $account->getClientId(),
            'tid'         => $account->getTerminalId(),
            'oosTranData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
                'wpAmount'     => 0,
                'mac'          => 'oE7zwV87uOc2DFpGPlr4jQRQ0z9LsxGw56c7vaiZkTo=',
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     * @param               $order
     *
     * @return array
     */
    private function getSampleCancelXMLData(AbstractPosAccount $account, $order): array
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
        if (isset($order->ref_ret_num)) {
            $requestData['reverse']['hostLogKey'] = $order->ref_ret_num;
        } else {
            $requestData['reverse']['orderID'] = 'TDSC000000002020110828BC';
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
    private function getSampleNonSecurePaymentRequestData(AbstractPosAccount $account, $order, AbstractCreditCard $card): array
    {
        return [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            'sale'             => [
                'orderID'      => $order->id,
                'installment'  => $order->installment,
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
     * @param               $order
     *
     * @return array
     */
    private function getSampleNonSecurePaymentPostRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            'capt'             => [
                'hostLogKey'   => $order->ref_ret_num,
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
    private function getSampleRefundXMLData(AbstractPosAccount $account, $order): array
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

        if (isset($order->ref_ret_num)) {
            $requestData['return']['hostLogKey'] = $order->ref_ret_num;
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
    private function getSample3DEnrollmentCheckRequestData(PosNetAccount $account, $order, AbstractCreditCard $card): array
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
                'installment'    => $order->installment,
                'XID'            => $this->requestDataMapper::formatOrderId($order->id),
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

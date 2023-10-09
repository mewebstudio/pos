<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Exception;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapperTest;
use Mews\Pos\Tests\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

class PayFlexCPV4PosTest extends TestCase
{
    /** @var PayFlexAccount */
    private $account;

    /** @var PayFlexCPV4Pos */
    private $pos;

    private $config;

    /** @var AbstractCreditCard */
    private $card;

    /** @var array */
    private $order = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $this->account = AccountFactory::createPayFlexAccount(
            'vakifbank-cp',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            PosInterface::MODEL_3D_SECURE
        );


        $this->order = [
            'id'          => 'order222',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'rand'        => microtime(true),
            'ip'          => '127.0.0.1',
        ];

        $this->pos = PosFactory::createPosGateway($this->account, $this->config, new EventDispatcher());

        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '2021', '12', '122', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
    }

    /**
     * @return void
     */
    public function testInit(): void
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getQueryAPIUrl());
        $this->assertNotEmpty($this->pos->getCurrencies());
    }

    public function testGet3DFormDataSuccess(): void
    {
        $crypt          = PosFactory::getGatewayCrypt(PayFlexCPV4Pos::class, new NullLogger());
        $requestMapper  = PosFactory::getGatewayRequestMapper(PayFlexCPV4Pos::class, $this->createMock(EventDispatcherInterface::class), $crypt, []);
        $responseMapper = PosFactory::getGatewayResponseMapper(PayFlexCPV4Pos::class, $requestMapper, new NullLogger());
        $serializer = PosFactory::getGatewaySerializer(PayFlexCPV4Pos::class);

        $posMock = $this->getMockBuilder(PayFlexCPV4Pos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $serializer,
                $this->createMock(EventDispatcherInterface::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['registerPayment'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->expects($this->once())->method('registerPayment')
            ->willReturn(PayFlexCPV4PosRequestDataMapperTest::threeDFormDataProvider()->current()['queryParams']);

        $result = $posMock->get3DFormData($this->order, PosInterface::MODEL_3D_SECURE, PosInterface::TX_PAY, $this->card);

        $this->assertSame(PayFlexCPV4PosRequestDataMapperTest::threeDFormDataProvider()->current()['expected'], $result);
    }

    public function testGet3DFormDataFail(): void
    {
        $this->expectException(Exception::class);
        $posMock = $this->getMockBuilder(PayFlexCPV4Pos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $this->createMock(PayFlexCPV4PosRequestDataMapper::class),
                $this->createMock(PayFlexCPV4PosResponseDataMapper::class),
                $this->createMock(SerializerInterface::class),
                $this->createMock(EventDispatcherInterface::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['registerPayment'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->expects($this->once())->method('registerPayment')
            ->willReturn([
                'CommonPaymentUrl' => null,
                'PaymentToken'     => null,
                'ErrorCode'        => '5007',
                'ResponseMessage'  => 'Güvenlik Numarası Hatalı',
            ]);

        $posMock->get3DFormData($this->order, PosInterface::MODEL_3D_SECURE, PosInterface::TX_PAY, $this->card);
    }

    public function testMake3dPayPaymentFail(): void
    {
        $failResponseData = iterator_to_array(
            PayFlexCPV4PosResponseDataMapperTest::threesDPayResponseSamplesProvider()
                                              )['fail_response_from_gateway_1']['bank_response'];
        $request = Request::create('', 'GET', $failResponseData);

        $requestMapper  = $this->createMock(PayFlexCPV4PosRequestDataMapper::class);
        $requestMapper->expects($this->never())
            ->method('create3DPaymentStatusRequestData');


        $responseMapper = $this->createMock(PayFlexCPV4PosResponseDataMapper::class);
        $responseMapper->expects($this->once())
            ->method('map3DPayResponseData')->with($failResponseData);

        $pos = new PayFlexCPV4Pos(
            [],
            $this->account,
            $requestMapper,
            $responseMapper,
            $this->createMock(SerializerInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            HttpClientFactory::createDefaultHttpClient(),
            new NullLogger());

        $pos->make3DPayPayment($request);
    }

    public function testMake3dPayPaymentSuccess(): void
    {
        $bankResponses = iterator_to_array(PayFlexCPV4PosResponseDataMapperTest::threesDPayResponseSamplesProvider());
        $bankQueryResponse = [
            'Rc' => '0000',
            'AuthCode' => '368513',
            'Message' => 'İŞLEM BAŞARILI',
            'TransactionId' => '28d2b9c27af545f48d49afc300db246b',
            'PaymentToken' => 'c6b7cecc2a1846088a4eafc300db246b',
            'MaskedPan' => '49384601****4205',
        ];
        $bankApiResponse = $bankResponses['success_response_from_gateway_1']['bank_response'];

        $request = Request::create('', 'GET', $bankQueryResponse);

        $requestMapper  = $this->createMock(PayFlexCPV4PosRequestDataMapper::class);
        $requestMapper->expects($this->once())
            ->method('create3DPaymentStatusRequestData')->with($this->account, $bankQueryResponse);

        $responseMapper = $this->createMock(PayFlexCPV4PosResponseDataMapper::class);
        $responseMapper->expects($this->once())
            ->method('map3DPayResponseData');

        $posMock = $this->getMockBuilder(PayFlexCPV4Pos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                $this->createMock(SerializerInterface::class),
                $this->createMock(EventDispatcherInterface::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['send', 'getQueryAPIUrl'])
            ->getMock();
        $posMock->expects($this->once())->method('getQueryAPIUrl')->willReturn($this->pos->getQueryAPIUrl());
        $posMock->expects($this->once())->method('send')->willReturn([$bankApiResponse]);

        $posMock->make3DPayPayment($request);
    }
}

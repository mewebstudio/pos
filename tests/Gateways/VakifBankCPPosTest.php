<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Exception;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifBankCPPosResponseDataMapper;
use Mews\Pos\DataMapper\VakifBankCPPosRequestDataMapper;
use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\HttpClientFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\VakifBankCPPos;
use Mews\Pos\Tests\DataMapper\ResponseDataMapper\VakifBankCPPosResponseDataMapperTest;
use Mews\Pos\Tests\DataMapper\VakifBankCPPosRequestDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

class VakifBankCPPosTest extends TestCase
{
    /** @var VakifBankAccount */
    private $account;

    /** @var VakifBankCPPos */
    private $pos;

    private $config;

    /** @var AbstractCreditCard */
    private $card;

    /** @var array */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createVakifBankAccount(
            'vakifbank-cp',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            AbstractGateway::MODEL_3D_SECURE
        );


        $this->order = [
            'id'          => 'order222',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'rand'        => microtime(true),
            'extraData'   => microtime(true),
            'ip'          => '127.0.0.1',
        ];

        $this->pos = PosFactory::createPosGateway($this->account);

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

    /**
     * @return void
     */
    public function testPrepare(): void
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());

        $this->pos->prepare($this->order, AbstractGateway::TX_POST_PAY);
    }

    public function testGet3DFormDataSuccess(): void
    {
        $crypt          = PosFactory::getGatewayCrypt(VakifBankCPPos::class, new NullLogger());
        $requestMapper  = PosFactory::getGatewayRequestMapper(VakifBankCPPos::class, [], $crypt);
        $responseMapper = PosFactory::getGatewayResponseMapper(VakifBankCPPos::class, $requestMapper, new NullLogger());

        $posMock = $this->getMockBuilder(VakifBankCPPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['registerPayment'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $posMock->expects($this->once())->method('registerPayment')
            ->willReturn(VakifBankCPPosRequestDataMapperTest::threeDFormDataProvider()->current()['queryParams']);

        $result = $posMock->get3DFormData();

        $this->assertSame(VakifBankCPPosRequestDataMapperTest::threeDFormDataProvider()->current()['expected'], $result);
    }

    public function testGet3DFormDataFail(): void
    {
        $this->expectException(Exception::class);
        $posMock = $this->getMockBuilder(VakifBankCPPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $this->createMock(VakifBankCPPosRequestDataMapper::class),
                $this->createMock(VakifBankCPPosResponseDataMapper::class),
                HttpClientFactory::createDefaultHttpClient(),
                new NullLogger()
            ])
            ->onlyMethods(['registerPayment'])
            ->getMock();
        $posMock->setTestMode(true);
        $posMock->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $posMock->expects($this->once())->method('registerPayment')
            ->willReturn([
                'CommonPaymentUrl' => null,
                'PaymentToken'     => null,
                'ErrorCode'        => '5007',
                'ResponseMessage'  => 'Güvenlik Numarası Hatalı',
            ]);

        $posMock->get3DFormData();
    }

    public function testMake3dPayPaymentFail(): void
    {
        $failResponseData = iterator_to_array(
            VakifBankCPPosResponseDataMapperTest::threesDPayResponseSamplesProvider()
                                              )['fail_response_from_gateway_1']['bank_response'];
        $request = Request::create('', 'GET', $failResponseData);

        $requestMapper  = $this->createMock(VakifBankCPPosRequestDataMapper::class);
        $requestMapper->expects($this->never())
            ->method('create3DPaymentStatusRequestData');

        $responseMapper = $this->createMock(VakifBankCPPosResponseDataMapper::class);
        $responseMapper->expects($this->once())
            ->method('map3DPayResponseData')->with($failResponseData);

        $pos = new VakifBankCPPos(
            [],
            $this->account,
            $requestMapper,
            $responseMapper,
            HttpClientFactory::createDefaultHttpClient(),
            new NullLogger());

        $pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);

        $pos->make3DPayPayment($request);
    }

    public function testMake3dPayPaymentSuccess(): void
    {
        $bankResponses = iterator_to_array(VakifBankCPPosResponseDataMapperTest::threesDPayResponseSamplesProvider());
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

        $requestMapper  = $this->createMock(VakifBankCPPosRequestDataMapper::class);
        $requestMapper->expects($this->once())
            ->method('create3DPaymentStatusRequestData')->with($this->account, $bankQueryResponse);

        $responseMapper = $this->createMock(VakifBankCPPosResponseDataMapper::class);
        $responseMapper->expects($this->once())
            ->method('map3DPayResponseData');

        $posMock = $this->getMockBuilder(VakifBankCPPos::class)
            ->setConstructorArgs([
                [],
                $this->account,
                $requestMapper,
                $responseMapper,
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

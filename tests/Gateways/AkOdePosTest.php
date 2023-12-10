<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Client\HttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\AkOdePosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AkOdePosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\AkOdePos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\DataMapper\RequestDataMapper\AkOdePosRequestDataMapperTest;
use Mews\Pos\Tests\DataMapper\ResponseDataMapper\AkOdePosResponseDataMapperTest;
use Mews\Pos\Tests\Serializer\AkOdePosSerializerTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\AkOdePos
 */
class AkOdePosTest extends TestCase
{
    public array $config;

    public CreditCardInterface $card;

    private AkOdePosAccount $account;

    /** @var AkOdePos */
    private PosInterface $pos;

    /** @var AkOdePosRequestDataMapper & MockObject */
    private MockObject $requestMapperMock;

    /** @var ResponseDataMapperInterface & MockObject */
    private MockObject $responseMapperMock;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    /** @var HttpClient & MockObject */
    private MockObject $httpClientMock;

    /** @var LoggerInterface & MockObject */
    private MockObject $loggerMock;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $eventDispatcherMock;

    /** @var SerializerInterface & MockObject */
    private MockObject $serializerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'AKBANK T.A.S.',
            'class'             => AkOdePos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://ent.akodepos.com/api/Payment',
                'gateway_3d'      => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
                'gateway_3d_host' => 'https://ent.akodepos.com/api/Payment/threeDSecure',
            ],
        ];

        $this->account = AccountFactory::createAkOdePosAccount(
            'akode',
            '1000000494',
            'POS_ENT_Test_001',
            'POS_ENT_Test_001!*!*',
        );

        $this->requestMapperMock   = $this->createMock(AkOdePosRequestDataMapper::class);
        $this->responseMapperMock  = $this->createMock(ResponseDataMapperInterface::class);
        $this->serializerMock      = $this->createMock(SerializerInterface::class);
        $this->cryptMock           = $this->createMock(CryptInterface::class);
        $this->httpClientMock      = $this->createMock(HttpClient::class);
        $this->loggerMock          = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->pos = new AkOdePos(
            $this->config,
            $this->account,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->serializerMock,
            $this->eventDispatcherMock,
            $this->httpClientMock,
            $this->loggerMock,
        );

        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create($this->pos, '5555444433332222', '21', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    public function testInit(): void
    {
        $this->assertEquals($this->config, $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiURL(string $txType, string $paymentModel, string $expected): void
    {
        $actual = $this->pos->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    public function testGet3DGatewayURL(): void
    {
        $actual = $this->pos->get3DGatewayURL();

        $this->assertSame(
            'https://ent.akodepos.com/api/Payment/ProcessCardForm',
            $actual
        );
    }

    public function testGet3DHostGatewayURL(): void
    {
        $sessionId = 'A2A6E942BD2AE4A68BC42FE99D1BC917D67AFF54AB2BA44EBA675843744187708';
        $actual    = $this->pos->get3DHostGatewayURL($sessionId);

        $this->assertSame(
            'https://ent.akodepos.com/api/Payment/threeDSecure/A2A6E942BD2AE4A68BC42FE99D1BC917D67AFF54AB2BA44EBA675843744187708',
            $actual
        );
    }

    /**
     * @dataProvider make3DPayPaymentDataProvider
     */
    public function testMake3DPayPayment(Request $request, array $expectedResponse, bool $isSuccess): void
    {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->willReturn(true);

        $this->requestMapperMock->expects(self::once())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

        $this->pos->make3DPayPayment($request);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider make3DPayPaymentDataProvider
     */
    public function testMake3DHostPayment(Request $request, array $expectedResponse, bool $isSuccess): void
    {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->willReturn(true);

        $this->requestMapperMock->expects(self::once())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

        $this->pos->make3DHostPayment($request);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider make3DPayPaymentDataProvider
     */
    public function testMake3DPayment(Request $request): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->make3DPayment($request, [], PosInterface::TX_PAY, $this->card);
    }

    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(
        array  $order,
        string $paymentModel,
        string $txType,
        bool   $isWithCard,
        array  $requestData,
        string $encodedRequestData,
        string $responseData,
        array  $decodedResponseData,
        array  $formData
    ): void
    {

        $card = $isWithCard ? $this->card : null;
        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with($this->pos->getAccount(), $order, $paymentModel, $txType)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            'https://ent.akodepos.com/api/Payment/threeDPayment',
            $requestData,
            $encodedRequestData,
            $responseData,
            $decodedResponseData,
        );

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $decodedResponseData,
                $paymentModel,
                $txType,
                'https://ent.akodepos.com/api/Payment/ProcessCardForm',
                $card
            )
            ->willReturn($formData);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $card);

        $this->assertSame($actual, $formData);
    }

    /**
     * @dataProvider statusDataProvider
     */
    public function testStatus(
        array  $order,
        array  $requestData,
        string $encodedRequest,
        string $responseContent,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void
    {
        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd($this->isInstanceOf(RequestDataPreparedEvent::class)));

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            PosInterface::TX_STATUS,
            'https://ent.akodepos.com/api/Payment/inquiry',
            $requestData,
            $encodedRequest,
            $responseContent,
            $decodedResponse
        );

        $this->pos->status($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $result = $this->pos->getResponse();
        $this->assertSame($result, $mappedResponse);
    }


    /**
     * @dataProvider cancelDataProvider
     */
    public function testCancel(
        array  $order,
        array  $requestData,
        string $encodedRequest,
        string $responseContent,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void
    {
        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd($this->isInstanceOf(RequestDataPreparedEvent::class)));

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            PosInterface::TX_CANCEL,
            'https://ent.akodepos.com/api/Payment/void',
            $requestData,
            $encodedRequest,
            $responseContent,
            $decodedResponse
        );

        $this->pos->cancel($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $result = $this->pos->getResponse();
        $this->assertSame($result, $mappedResponse);
    }

    /**
     * @dataProvider refundDataProvider
     */
    public function testRefund(
        array  $order,
        array  $requestData,
        string $encodedRequest,
        string $responseContent,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void
    {
        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd($this->isInstanceOf(RequestDataPreparedEvent::class)));

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            PosInterface::TX_REFUND,
            'https://ent.akodepos.com/api/Payment/refund',
            $requestData,
            $encodedRequest,
            $responseContent,
            $decodedResponse
        );

        $this->pos->refund($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $result = $this->pos->getResponse();
        $this->assertSame($result, $mappedResponse);
    }


    /**
     * @dataProvider historyDataProvider
     */
    public function testHistory(
        array  $order,
        array  $requestData,
        string $encodedRequest,
        string $responseContent,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void
    {
        $this->requestMapperMock->expects(self::once())
            ->method('createHistoryRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd($this->isInstanceOf(RequestDataPreparedEvent::class)));

        $this->responseMapperMock->expects(self::once())
            ->method('mapHistoryResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            PosInterface::TX_HISTORY,
            'https://ent.akodepos.com/api/Payment/history',
            $requestData,
            $encodedRequest,
            $responseContent,
            $decodedResponse
        );

        $this->pos->history($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $result = $this->pos->getResponse();
        $this->assertSame($result, $mappedResponse);
    }

    public static function statusDataProvider(): iterable
    {
        yield [
            'order'               => AkOdePosRequestDataMapperTest::statusRequestDataProvider()[0]['order'],
            'requestData'         => AkOdePosRequestDataMapperTest::statusRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(AkOdePosRequestDataMapperTest::statusRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => \json_encode(AkOdePosResponseDataMapperTest::statusResponseDataProvider()['success1']['responseData'], JSON_THROW_ON_ERROR),
            'decodedResponseData' => AkOdePosResponseDataMapperTest::statusResponseDataProvider()['success1']['responseData'],
            'mappedResponse'      => AkOdePosResponseDataMapperTest::statusResponseDataProvider()['success1']['expectedData'],
            'isSuccess'           => true,
        ];
    }

    public static function cancelDataProvider(): iterable
    {
        yield [
            'order'               => AkOdePosRequestDataMapperTest::cancelRequestDataProvider()[0]['order'],
            'requestData'         => AkOdePosRequestDataMapperTest::cancelRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(AkOdePosRequestDataMapperTest::cancelRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => \json_encode(AkOdePosResponseDataMapperTest::cancelDataProvider()['success1']['responseData'], JSON_THROW_ON_ERROR),
            'decodedResponseData' => AkOdePosResponseDataMapperTest::statusResponseDataProvider()['success1']['responseData'],
            'mappedResponse'      => AkOdePosResponseDataMapperTest::statusResponseDataProvider()['success1']['expectedData'],
            'isSuccess'           => true,
        ];
    }

    public static function refundDataProvider(): iterable
    {
        yield [
            'order'               => AkOdePosRequestDataMapperTest::refundRequestDataProvider()[0]['order'],
            'requestData'         => AkOdePosRequestDataMapperTest::refundRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(AkOdePosRequestDataMapperTest::refundRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => \json_encode(AkOdePosResponseDataMapperTest::refundDataProvider()['success1']['responseData'], JSON_THROW_ON_ERROR),
            'decodedResponseData' => AkOdePosResponseDataMapperTest::refundDataProvider()['success1']['responseData'],
            'mappedResponse'      => AkOdePosResponseDataMapperTest::refundDataProvider()['success1']['expectedData'],
            'isSuccess'           => true,
        ];
    }

    public static function historyDataProvider(): iterable
    {
        yield [
            'order'               => AkOdePosRequestDataMapperTest::historyRequestDataProvider()[0]['order'],
            'requestData'         => AkOdePosRequestDataMapperTest::historyRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(AkOdePosRequestDataMapperTest::historyRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => \json_encode(AkOdePosResponseDataMapperTest::historyDataProvider()['success_only_payment_transaction']['responseData'], JSON_THROW_ON_ERROR),
            'decodedResponseData' => AkOdePosResponseDataMapperTest::historyDataProvider()['success_only_payment_transaction']['responseData'],
            'mappedResponse'      => AkOdePosResponseDataMapperTest::historyDataProvider()['success_only_payment_transaction']['expectedData'],
            'isSuccess'           => true,
        ];
    }

    public static function threeDFormDataProvider(): iterable
    {
        yield [
            'order'               => AkOdePosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['order'],
            'paymentModel'        => PosInterface::MODEL_3D_PAY,
            'txType'              => PosInterface::TX_PAY,
            'isWithCard'          => true,
            'requestData'         => AkOdePosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['expected'],
            'encodedRequestData'  => \json_encode(AkOdePosRequestDataMapperTest::statusRequestDataProvider()[0]['expected'], JSON_THROW_ON_ERROR),
            'responseData'        => AkOdePosSerializerTest::decodeDataProvider()['payment_register']['input'],
            'decodedResponseData' => AkOdePosSerializerTest::decodeDataProvider()['payment_register']['decoded'],
            'formData'            => AkOdePosRequestDataMapperTest::threeDFormDataProvider()['3d_pay_form_data']['expected'],
        ];
    }


    public static function make3DPayPaymentDataProvider(): array
    {
        return [
            'auth_fail' => [
                'request'   => Request::create('', 'POST', AkOdePosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['paymentData']),
                'expected'  => AkOdePosResponseDataMapperTest::threeDPayPaymentDataProvider()['auth_fail']['expectedData'],
                'isSuccess' => false,
            ],
            'success'   => [
                'request'   => Request::create('', 'POST', AkOdePosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData']),
                'expected'  => AkOdePosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['expectedData'],
                'isSuccess' => true,
            ],
        ];
    }

    private function configureClientResponse(
        string $txType,
        string $apiUrl,
        array  $requestData,
        string $encodedRequestData,
        string $responseContent,
        array  $decodedResponse
    ): void
    {
        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with($requestData, $txType)
            ->willReturn($encodedRequestData);
        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with($responseContent, $txType)
            ->willReturn($decodedResponse);

        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock   = $this->createMock(StreamInterface::class);
        $streamMock->expects(self::once())
            ->method('getContents')
            ->willReturn($responseContent);
        $responseMock->expects(self::once())
            ->method('getBody')
            ->willReturn($streamMock);
        $this->httpClientMock->expects(self::once())
            ->method('post')
            ->with($apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body'    => $encodedRequestData,
            ])
            ->willReturn($responseMock);
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_PAY,
                'paymentModel' => PosInterface::MODEL_3D_PAY,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPayment',
            ],
            [
                'txType'       => PosInterface::TX_PRE_PAY,
                'paymentModel' => PosInterface::MODEL_3D_PAY,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPreAuth',
            ],
            [
                'txType'       => PosInterface::TX_PAY,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPayment',
            ],
            [
                'txType'       => PosInterface::TX_PRE_PAY,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => 'https://ent.akodepos.com/api/Payment/threeDPreAuth',
            ],
            [
                'txType'       => PosInterface::TX_PAY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/Payment',
            ],
            [
                'txType'       => PosInterface::TX_POST_PAY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/postAuth',
            ],
            [
                'txType'       => PosInterface::TX_STATUS,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/inquiry',
            ],
            [
                'txType'       => PosInterface::TX_CANCEL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/void',
            ],
            [
                'txType'       => PosInterface::TX_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/refund',
            ],
            [
                'txType'       => PosInterface::TX_HISTORY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://ent.akodepos.com/api/Payment/history',
            ],
        ];
    }
}

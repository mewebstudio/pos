<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\HttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapperTest;
use Mews\Pos\Tests\Unit\HttpClientTestTrait;
use Mews\Pos\Tests\Unit\Serializer\KuveytPosSerializerTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Mews\Pos\Gateways\KuveytPos
 */
class KuveytPosTest extends TestCase
{
    use HttpClientTestTrait;

    private KuveytPosAccount $account;

    private array $config;

    private CreditCardInterface $card;

    private array $order;

    /** @var KuveytPos */
    private PosInterface $pos;

    /** @var KuveytPosRequestDataMapper & MockObject */
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

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'kuveyt-pos',
            'class'             => KuveytPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate',
                'gateway_3d'  => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate',
                'query_api'   => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            ],
        ];

        $this->account = AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '496',
            'apiuser1',
            '400235',
            'Api123'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'amount'      => 10.01,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'ip'          => '127.0.0.1',
            'lang'        => PosInterface::LANG_TR,
        ];

        $this->requestMapperMock   = $this->createMock(KuveytPosRequestDataMapper::class);
        $this->responseMapperMock  = $this->createMock(ResponseDataMapperInterface::class);
        $this->serializerMock      = $this->createMock(SerializerInterface::class);
        $this->cryptMock           = $this->createMock(CryptInterface::class);
        $this->httpClientMock      = $this->createMock(HttpClient::class);
        $this->loggerMock          = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = new KuveytPos(
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

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '4155650100416111',
            25,
            1,
            '123',
            'John Doe',
            CreditCardInterface::CARD_TYPE_VISA
        );
    }

    /**
     * @return void
     */
    public function testInit(): void
    {
        $this->requestMapperMock->expects(self::once())
            ->method('getCurrencyMappings')
            ->willReturn([PosInterface::CURRENCY_TRY => '949']);
        $this->assertSame([PosInterface::CURRENCY_TRY], $this->pos->getCurrencies());
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
    }

    /**
     * @return void
     */
    public function testSetTestMode(): void
    {
        $this->pos->setTestMode(false);
        $this->assertFalse($this->pos->isTestMode());
        $this->pos->setTestMode(true);
        $this->assertTrue($this->pos->isTestMode());
    }

    /**
     * @return void
     */
    public function testGetCommon3DFormDataSuccessResponse(): void
    {
        $response     = 'bank-api-html-response';
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $card         = $this->card;

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with(['form-data'], $txType)
            ->willReturn('encoded-request-data');

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with($response, $txType)
            ->willReturn(['form_inputs' => ['form-inputs'], 'gateway' => 'form-action-url']);
        $this->prepareClient(
            $this->httpClientMock,
            $response,
            'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate',
            [
                'body'    => 'encoded-request-data',
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF-8',
                ],
            ],
        );

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch');

        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with(
                $this->pos->getAccount(),
                $this->order,
                $paymentModel,
                $txType,
                $card
            )
            ->willReturn(['form-data']);

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                ['form-inputs'],
                $paymentModel,
                $txType,
                'form-action-url',
                $card
            )
            ->willReturn(['3d-form-data']);
        $result = $this->pos->get3DFormData($this->order, $paymentModel, $txType, $card);

        $this->assertSame(['3d-form-data'], $result);
    }

    /**
     * @dataProvider make3DPaymentDataProvider
     */
    public function testMake3DPayment(
        array   $order,
        string  $txType,
        Request $request,
        array   $decodedRequest,
        array   $paymentResponse,
        array   $expectedResponse,
        bool    $is3DSuccess,
        bool    $isSuccess
    ): void
    {
        if ($is3DSuccess) {
            $this->cryptMock->expects(self::once())
                ->method('check3DHash')
                ->with($this->account, $decodedRequest)
                ->willReturn(true);
        }

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($decodedRequest)
            ->willReturn('3d-status');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('3d-status')
            ->willReturn($is3DSuccess);

        $create3DPaymentRequestData = [
            'create3DPaymentRequestData',
        ];


        if ($is3DSuccess) {
            $this->requestMapperMock->expects(self::once())
                ->method('create3DPaymentRequestData')
                ->with($this->account, $order, $txType, $decodedRequest)
                ->willReturn($create3DPaymentRequestData);
            $this->prepareClient(
                $this->httpClientMock,
                'response-body',
                $this->config['gateway_endpoints']['payment_api'],
                [
                    'body'    => 'request-body',
                    'headers' => [
                        'Content-Type' => 'text/xml; charset=UTF-8',
                    ],
                ]
            );

            $this->serializerMock->expects(self::once())
                ->method('encode')
                ->with($create3DPaymentRequestData, $txType)
                ->willReturn('request-body');

            $this->serializerMock->expects(self::exactly(2))
                ->method('decode')
                ->willReturnMap([
                    [
                        urldecode($request->request->get('AuthenticationResponse')),
                        $txType,
                        $decodedRequest,
                    ],
                    [
                        'response-body',
                        $txType,
                        $paymentResponse,
                    ],
                ]);

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($decodedRequest, $paymentResponse, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($decodedRequest, null, $txType, $order)
                ->willReturn($expectedResponse);
            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
            $this->serializerMock->expects(self::never())
                ->method('encode');
            $this->serializerMock->expects(self::once())
                ->method('decode')
                ->with(urldecode($request->request->get('AuthenticationResponse')), $txType)
                ->willReturn($decodedRequest);
        }

        $this->pos->make3DPayment($request, $order, $txType);

        $result = $this->pos->getResponse();
        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    public function testMakeRegularPayment(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->pos->makeRegularPayment([], $this->card, PosInterface::TX_TYPE_PAY_AUTH);
    }

    public static function make3DPaymentDataProvider(): array
    {
        return [
            'auth_fail'                    => [
                'order'           => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['order'],
                'txType'          => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    ['AuthenticationResponse' => KuveytPosSerializerTest::decodeHtmlDataProvider()['3d_auth_fail']['html']]
                ),
                'decodedRequest'  => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['threeDResponseData'],
                'paymentResponse' => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['paymentData'],
                'expected'        => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['expectedData'],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            '3d_auth_success_payment_fail' => [
                'order'           => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail_1']['order'],
                'txType'          => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail_1']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    ['AuthenticationResponse' => KuveytPosSerializerTest::decodeHtmlDataProvider()['3d_auth_success_1']['html']]
                ),
                'decodedRequest'  => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail_1']['threeDResponseData'],
                'paymentResponse' => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail_1']['paymentData'],
                'expected'        => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail_1']['expectedData'],
                'is3DSuccess'     => true,
                'isSuccess'       => false,
            ],
            'success'                      => [
                'order'           => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['order'],
                'txType'          => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['txType'],
                'request'         => Request::create(
                    '',
                    'POST',
                    ['AuthenticationResponse' => KuveytPosSerializerTest::decodeHtmlDataProvider()['3d_auth_success_1']['html']]
                ),
                'decodedRequest'  => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData'],
                'paymentResponse' => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['paymentData'],
                'expected'        => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['expectedData'],
                'is3DSuccess'     => true,
                'isSuccess'       => true,
            ],
        ];
    }
}

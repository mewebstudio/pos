<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Exception;
use Mews\Pos\Client\HttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexV4PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\PayFlexV4PosRequestDataMapperTest;
use Mews\Pos\Tests\Unit\HttpClientTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Gateways\PayFlexV4Pos
 */
class PayFlexV4PosTest extends TestCase
{
    use HttpClientTestTrait;

    private PayFlexAccount $account;

    /** @var PayFlexV4Pos */
    private PosInterface $pos;

    private array $config;

    private CreditCardInterface $card;

    private array $order = [];

    /** @var RequestDataMapperInterface & MockObject */
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
            'name'  => 'VakifBank-VPOS',
            'class' => PayFlexV4Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
                'gateway_3d'      => 'https://3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspxs',
                'query_api'       => 'https://sanalpos.vakifbank.com.tr/v4/UIWebService/Search.aspx',
            ],
        ];

        $this->account = AccountFactory::createPayFlexAccount(
            'vakifbank',
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
            'ip'          => '127.0.0.1',
        ];

        $this->requestMapperMock   = $this->createMock(PayFlexV4PosRequestDataMapper::class);
        $this->responseMapperMock  = $this->createMock(ResponseDataMapperInterface::class);
        $this->serializerMock      = $this->createMock(SerializerInterface::class);
        $this->cryptMock           = $this->createMock(CryptInterface::class);
        $this->httpClientMock      = $this->createMock(HttpClient::class);
        $this->loggerMock          = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = new PayFlexV4Pos(
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

        $this->card = CreditCardFactory::createForGateway($this->pos, '5555444433332222', '2021', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
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
     *
     * @throws Exception
     */
    public function testGet3DFormDataEnrollmentFail(): void
    {
        $this->expectException(Exception::class);
        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with($this->pos->getAccount(), $this->order, $this->card)
            ->willReturn(['request-data']);

        $this->serializerMock->expects(self::never())
            ->method('encode');

        $this->prepareClient(
            $this->httpClientMock,
            'response-content',
            $this->config['gateway_endpoints']['gateway_3d'],
            ['form_params' => ['request-data']],
        );

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-content', PosInterface::TX_TYPE_PAY_AUTH)
            ->willReturn(self::getSampleEnrollmentFailResponseDataProvider());

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $this->pos->get3DFormData($this->order, PosInterface::MODEL_3D_SECURE, PosInterface::TX_TYPE_PAY_AUTH, $this->card);
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testGet3DFormDataSuccess(): void
    {
        $enrollmentResponse = PayFlexV4PosRequestDataMapperTest::getSampleEnrollmentSuccessResponseDataProvider();
        $txType = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $card = $this->card;
        $order = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('create3DEnrollmentCheckRequestData')
            ->with($this->pos->getAccount(), $order, $card)
            ->willReturn(['request-data']);

        $this->serializerMock->expects(self::never())
            ->method('encode');

        $this->prepareClient(
            $this->httpClientMock,
            'response-content',
            $this->config['gateway_endpoints']['gateway_3d'],
            ['form_params' => ['request-data']],
        );

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('response-content', $txType)
            ->willReturn($enrollmentResponse);

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                null,
                $paymentModel,
                $txType,
                '',
                null,
                $enrollmentResponse['Message']['VERes']
            )
            ->willReturn(['3d-form-data']);

        $result = $this->pos->get3DFormData($order, $paymentModel, $txType, $card);

        $this->assertSame(['3d-form-data'], $result);
    }

    public static function getSampleEnrollmentFailResponseDataProvider(): array
    {
        return [
            'Message'                   => [
                'VERes' => [
                    'Status' => 'E',
                ],
            ],
            'VerifyEnrollmentRequestId' => '0aebb0757acccae6fba75b2e4d78cecf',
            'MessageErrorCode'          => '2005',
            'ErrorMessage'              => 'Merchant cannot be found for this bank',
        ];
    }
}

<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional;

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;
use Monolog\Test\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ParamPosTest extends TestCase
{
    use PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var ParamPos */
    private PosInterface $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../config/pos_test.php';

        $account = AccountFactory::createParamPosAccount(
            'param-pos',
            10738,
            'Test',
            'Test',
            '0c13d406-873b-403b-9c09-a5766840d98c'
        );

        $this->eventDispatcher = new EventDispatcher();

        $this->pos = PosFactory::createPosGateway($account, $config, $this->eventDispatcher);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '5456165456165454',
            '26',
            '12',
            '000',
            'John Doe'
        );
    }

    public function testNonSecurePaymentSuccess(): array
    {
        $card = CreditCardFactory::createForGateway(
            $this->pos,
            '5818775818772285',
            '26',
            '12',
            '001',
            'John Doe'
        );
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $card
        );

        $response = $this->pos->getResponse();

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $this->pos->getResponse();
    }

    public function testNonSecureForeignCurrencyPaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);
        $order['currency'] = PosInterface::CURRENCY_USD;

        $card = CreditCardFactory::createForGateway(
            $this->pos,
            '4546711234567894',
            '26',
            '12',
            '000',
            'John Doe'
        );

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $card
        );

        $response = $this->pos->getResponse();

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $this->pos->getResponse();
    }

    public function testNonSecurePaymentWithInstallment(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);
        $order['installment'] = 2;

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $response = $this->pos->getResponse();

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);

        return $this->pos->getResponse();
    }

    public function testNonSecurePrePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_PRE_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
            $this->card
        );

        $response = $this->pos->getResponse();

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $this->pos->getResponse();
    }

    /**
     * @depends testNonSecurePrePaymentSuccess
     */
    public function testNonSecurePostPaymentSuccess(array $lastResponse): array
    {
        $order = $this->createPostPayOrder(\get_class($this->pos), $lastResponse);

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_POST_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_POST_AUTH,
            $this->card
        );

        $response = $this->pos->getResponse();

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $this->pos->getResponse();
    }

    /**
     * @depends testNonSecurePaymentSuccess
     */
    public function testStatusSuccess(array $lastResponse): array
    {
        $statusOrder = $this->createStatusOrder(\get_class($this->pos), $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_STATUS, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $this->pos->status($statusOrder);

        $this->assertTrue($this->pos->isSuccess());
        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    /**
     * @depends testNonSecurePaymentSuccess
     * @depends testStatusSuccess
     */
    public function testCancelSuccess(array $lastResponse): array
    {
        $cancelOrder = $this->createCancelOrder(\get_class($this->pos), $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $this->pos->cancel($cancelOrder);

        $this->assertTrue($this->pos->isSuccess());
        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    public function testCancelPrePay(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
            $this->card
        );

        $lastResponse = $this->pos->getResponse();

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');

        $cancelOrder = $this->createCancelOrder(\get_class($this->pos), $lastResponse);

        $this->pos->cancel($cancelOrder);

        $this->assertTrue($this->pos->isSuccess());
    }

    public function testGet3DFormData(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_SECURE);
        $card = CreditCardFactory::createForGateway(
            $this->pos,
            '5818775818772285',
            '26',
            '12',
            '001',
            'John Doe'
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
            }
        );
        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_TYPE_PAY_AUTH,
            $card
        );

        $this->assertIsString($formData);
        $this->assertTrue($eventIsThrown);
    }

    public function testGet3DFormDataForeignCurrency(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_SECURE);
        $order['currency'] = PosInterface::CURRENCY_USD;
        $card = CreditCardFactory::createForGateway(
            $this->pos,
            '4546711234567894',
            '26',
            '12',
            '000',
            'John Doe'
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
            }
        );
        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_TYPE_PAY_AUTH,
            $card
        );

        $this->assertIsArray($formData);
        $this->assertNotEmpty($formData['gateway']);
        $this->assertTrue($eventIsThrown);
    }

    public function testGet3DHostFormData(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_HOST);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Header']);
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
            }
        );
        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_HOST,
            PosInterface::TX_TYPE_PAY_AUTH
        );

        $this->assertIsArray($formData);
        $this->assertArrayHasKey('inputs', $formData);
        $this->assertNotEmpty($formData['inputs']);
        $this->assertTrue($eventIsThrown);
    }

    public function testRefund(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess());

        $lastResponse = $this->pos->getResponse();
        $this->assertTrue($this->pos->isSuccess(), $lastResponse['error_message'] ?? 'hata');

        $refundOrder = $this->createRefundOrder(\get_class($this->pos), $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_REFUND, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $this->pos->refund($refundOrder);
        $response = $this->pos->getResponse();
        // fails with error: Failed, Bu işlem geri alınamaz, lüften asıl işlemi iptal edin.
        $this->assertFalse($this->pos->isSuccess(), $response['error_message'] ?? 'hata');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    public function testCustomQuery(): void
    {
        $customQuery = [
            'TP_Ozel_Oran_Liste' => [
                '@xmlns' => 'https://turkpos.com.tr/',
            ],
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CUSTOM_QUERY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $this->pos->customQuery($customQuery);

        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertArrayHasKey('TP_Ozel_Oran_ListeResponse', $response);
        $this->assertArrayHasKey('TP_Ozel_Oran_ListeResult', $response['TP_Ozel_Oran_ListeResponse']);
        $this->assertArrayHasKey('DT_Bilgi', $response['TP_Ozel_Oran_ListeResponse']['TP_Ozel_Oran_ListeResult']);
        $this->assertTrue($eventIsThrown);
    }

    public function testHistorySuccess(): void
    {
        $historyOrder = $this->createHistoryOrder(\get_class($this->pos), [], '127.0.0.1');

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_HISTORY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $this->pos->history($historyOrder);

        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertTrue($eventIsThrown);
        $this->assertNotEmpty($response['transactions']);
    }
}

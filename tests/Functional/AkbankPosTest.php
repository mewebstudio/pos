<?php

/**
 * @license MIT
 */

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\Functional\PaymentTestTrait;
use Monolog\Test\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AkbankPosTest extends TestCase
{
    use PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var \Mews\Pos\Gateways\AkbankPos */
    private PosInterface $pos;

    /** @var \Mews\Pos\Gateways\AkbankPos */
    private PosInterface $recurringPos;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../config/pos_test.php';

        $account = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            '2023090417500272654BD9A49CF07574',
            '2023090417500284633D137A249DBBEB',
            '3230323330393034313735303032363031353172675f357637355f3273387373745f7233725f73323333383737335f323272383774767276327672323531355f',
        );

        $recurringAccount = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            '20230225213454627757B485BC1211C0',
            '20230225213454678B3D03B9C0057F40',
            '3230323330323235323133343534353438373832315f38747231375f67326776733233725f76723837725f3735727367673538313737383535337335765f7432',
        );

        $this->eventDispatcher = new EventDispatcher();

        $this->pos          = PosFactory::createPosGateway($account, $config, $this->eventDispatcher);
        $this->recurringPos = PosFactory::createPosGateway($recurringAccount, $config, $this->eventDispatcher);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '4355093000315232',
            '40',
            '11',
            '471',
        );
    }

    public function testNonSecurePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(9, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess());

        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $this->pos->getResponse();
    }

    /**
     * @depends testNonSecurePaymentSuccess
     */
    public function testCancelSuccess(array $lastResponse): array
    {
        $statusOrder = $this->createCancelOrder(\get_class($this->pos), $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->pos->cancel($statusOrder);

        $this->assertTrue($this->pos->isSuccess());
        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    /**
     * @depends testCancelSuccess
     */
    public function testOrderHistorySuccess(array $lastResponse): void
    {
        $historyOrder = $this->createOrderHistoryOrder(\get_class($this->pos), $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_ORDER_HISTORY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->pos->orderHistory($historyOrder);

        $this->assertTrue($this->pos->isSuccess());
        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
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
                $this->assertCount(3, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->pos->history($historyOrder);

        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertTrue($eventIsThrown);
        $this->assertNotEmpty($response['transactions']);
    }

    public function testNonSecurePrePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(
            PosInterface::MODEL_NON_SECURE,
            PosInterface::CURRENCY_TRY,
            30.0,
            3
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_PRE_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(9, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
            $this->card
        );

        $response = $this->pos->getResponse();

        $this->assertTrue($this->pos->isSuccess());

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
        $order         = $this->createPostPayOrder(\get_class($this->pos), $lastResponse);
        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_POST_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(8, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_POST_AUTH
        );

        $this->assertTrue($this->pos->isSuccess());
        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    /**
     * @depends testNonSecurePostPaymentSuccess
     */
    public function testRefundSuccess(array $lastResponse): array
    {
        $refundOrder = $this->createRefundOrder(\get_class($this->pos), $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_REFUND, $requestDataPreparedEvent->getTxType());
                $this->assertCount(7, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->pos->refund($refundOrder);

        $response = $this->pos->getResponse();
        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    public function testNonSecurePaymentRecurringSuccess(): array
    {
        $order = $this->createPaymentOrder(
            PosInterface::MODEL_NON_SECURE,
            PosInterface::CURRENCY_TRY,
            5,
            3,
            true,
        );

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(10, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->recurringPos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $response = $this->recurringPos->getResponse();

        $this->assertTrue($this->recurringPos->isSuccess());

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $this->recurringPos->getResponse();
    }

    /**
     * @depends testNonSecurePaymentRecurringSuccess
     */
    public function testCancelRecurringOrder(array $lastResponse): array
    {
        $statusOrder = [
            'recurring_id'                    => $lastResponse['recurring_id'],
            'recurringOrderInstallmentNumber' => 1,
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(7, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->recurringPos->cancel($statusOrder);

        $response = $this->recurringPos->getResponse();
        $this->assertTrue($this->recurringPos->isSuccess(), $response['error_message'] ?? null);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    /**
     * @depends testNonSecurePaymentRecurringSuccess
     */
    public function testCancelPendingRecurringOrder(array $lastResponse): array
    {
        $statusOrder = [
            'recurring_id'                    => $lastResponse['recurring_id'],
            'recurringOrderInstallmentNumber' => 2,
            'recurring_payment_is_pending'    => true,
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(7, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->recurringPos->cancel($statusOrder);

        $this->assertTrue($this->recurringPos->isSuccess());
        $response = $this->recurringPos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    /**
     * @depends testCancelPendingRecurringOrder
     */
    public function testCancelAllPendingRecurringOrder(array $lastResponse): array
    {
        $statusOrder = [
            'recurring_id'                    => $lastResponse['recurring_id'],
            'recurringOrderInstallmentNumber' => null,
            'recurring_payment_is_pending'    => true,
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->recurringPos->cancel($statusOrder);

        $this->assertTrue($this->recurringPos->isSuccess());
        $response = $this->recurringPos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    /**
     * @depends testCancelRecurringOrder
     */
    public function testRecurringOrderHistorySuccess(array $lastResponse): void
    {
        $historyOrder = $this->createOrderHistoryOrder(\get_class($this->recurringPos), $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_ORDER_HISTORY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->recurringPos->orderHistory($historyOrder);

        $this->assertTrue($this->recurringPos->isSuccess());
        $response = $this->recurringPos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }

    /**
     * @depends testCancelRecurringOrder
     */
    public function testRecurringHistorySuccess(): void
    {
        $historyOrder = $this->createHistoryOrder(\get_class($this->pos), [], '127.0.0.1');

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_HISTORY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(3, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->recurringPos->history($historyOrder);

        $response = $this->recurringPos->getResponse();
        $this->assertTrue($this->recurringPos->isSuccess());
        $this->assertIsArray($response);
        $this->assertTrue($eventIsThrown);
        $this->assertNotEmpty($response['transactions']);
    }

    public function testCustomQuery(): void
    {
        $customQuery = [
            'txnCode'     => '1020',
            'order'       => [
                'orderTrackId' => 'ae15a6c8-467e-45de-b24c-b98821a42667',
            ],
            'payByLink'   => [
                'linkTxnCode'       => '3000',
                'linkTransferType'  => 'SMS',
                'mobilePhoneNumber' => '5321234567',
            ],
            'transaction' => [
                'amount'       => 1.00,
                'currencyCode' => 949,
                'motoInd'      => 0,
                'installCount' => 1,
            ],
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CUSTOM_QUERY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(8, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->pos->customQuery($customQuery);

        $response = $this->pos->getResponse();

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertArrayHasKey('responseCode', $response);
        $this->assertTrue($eventIsThrown);
    }
}

<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional;

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\PosInterface;
use Monolog\Test\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PayForPosTest extends TestCase
{
    use PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var PayForPos */
    private PosInterface $pos;

    private array $lastResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../config/pos_test.php';

        $account = AccountFactory::createPayForAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            PosInterface::MODEL_3D_SECURE,
            '12345678'
        );
        $this->eventDispatcher = new EventDispatcher();

        $this->pos = PosFactory::createPosGateway($account, $config, $this->eventDispatcher);
        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create(
            $this->pos,
            '4155650100416111',
            '25',
            '1',
            '123',
            'John Doe',
            CreditCardInterface::CARD_TYPE_VISA
        );
    }

    public function testNonSecurePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder();

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $event->getTxType());
                $this->assertCount(16, $event->getRequestData());
            });

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
     * @depends testNonSecurePostPaymentSuccess
     */
    public function testStatusSuccess(array $lastResponse): array
    {
        $statusOrder = $this->createStatusOrder(\get_class($this->pos), $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_STATUS, $event->getTxType());
                $this->assertCount(8, $event->getRequestData());
            });

        $this->pos->status($statusOrder);

        $this->assertTrue($this->pos->isSuccess());
        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    /**
     * @depends testNonSecurePostPaymentSuccess
     * @depends testStatusSuccess
     */
    public function testCancelSuccess(array $lastResponse): array
    {
        $statusOrder = $this->createCancelOrder(\get_class($this->pos), $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $event->getTxType());
                $this->assertCount(9, $event->getRequestData());
            });

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
    public function testHistorySuccess(array $lastResponse): void
    {
        $historyOrder = $this->createHistoryOrder(\get_class($this->pos), $lastResponse, []);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_HISTORY, $event->getTxType());
                $this->assertCount(8, $event->getRequestData());
            });

        $this->pos->history($historyOrder);

        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }

    public function testNonSecurePrePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::CURRENCY_TRY, 2.01, 3);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_PRE_AUTH, $event->getTxType());
                $this->assertCount(16, $event->getRequestData());
            });

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
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
     * @depends testNonSecurePrePaymentSuccess
     */
    public function testNonSecurePostPaymentSuccess(array $lastResponse): array
    {
        $order = $this->createPostPayOrder(\get_class($this->pos), $lastResponse);
        $order['amount'] += .02;
        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_POST_AUTH, $event->getTxType());
                $this->assertCount(10, $event->getRequestData());
            });

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

        return $response;
    }

    public function testGet3DFormData(): void
    {
        $order = $this->createPaymentOrder();

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            Before3DFormHashCalculatedEvent::class,
            function (Before3DFormHashCalculatedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertCount(17, $event->getFormInputs());
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $event->getTxType());
                $formInputs = $event->getFormInputs();
                $formInputs['test_input'] = 'test_value';
                $event->setFormInputs($formInputs);
            });

        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_PAY,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );
        $this->assertCount(19, $formData['inputs']);
        $this->assertArrayHasKey('test_input', $formData['inputs']);
        $this->assertTrue($eventIsThrown);
    }
}

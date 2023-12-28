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
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;
use Monolog\Test\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class GarantiPosTest extends TestCase
{
    use PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var GarantiPos */
    private PosInterface $pos;

    private array $lastResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../config/pos_test.php';

        $account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            PosInterface::MODEL_3D_SECURE,
            '12345678',
            'PROVRFN',
            '123qweASD/'
        );

        $this->eventDispatcher = new EventDispatcher();

        $this->pos = PosFactory::createPosGateway($account, $config, $this->eventDispatcher);
        $this->pos->setTestMode(true);

        $this->card = CreditCardFactory::create(
            $this->pos,
            '5549608789641500',
            '27',
            '04',
            '464',
            'John Doe',
            CreditCardInterface::CARD_TYPE_MASTERCARD
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
                $this->assertCount(7, $event->getRequestData());
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
            function (RequestDataPreparedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_STATUS, $event->getTxType());
                $this->assertCount(6, $event->getRequestData());
            });

        $this->pos->status($statusOrder);

        $this->assertTrue($this->pos->isSuccess());
        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);

        return $lastResponse;
    }

    /**
     * @depends testNonSecurePaymentSuccess
     * @depends testStatusSuccess
     */
    public function testCancelSuccess(array $lastResponse): void
    {
        $statusOrder = $this->createCancelOrder(\get_class($this->pos), $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $event->getTxType());
                $this->assertCount(6, $event->getRequestData());
            });

        $this->pos->cancel($statusOrder);

        $this->assertTrue($this->pos->isSuccess());
        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }

    public function testNonSecurePrePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::CURRENCY_TRY, 3);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_PRE_AUTH, $event->getTxType());
                $this->assertCount(7, $event->getRequestData());
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
    public function testNonSecurePostPaymentSuccess(array $lastResponse): void
    {
        $order = $this->createPostPayOrder(\get_class($this->pos), $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_POST_AUTH, $event->getTxType());
                $this->assertCount(6, $event->getRequestData());
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
    }

    public function testGet3DFormData(): void
    {
        $order = $this->createPaymentOrder();

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            Before3DFormHashCalculatedEvent::class,
            function (Before3DFormHashCalculatedEvent $event) use (&$eventIsThrown) {
                $eventIsThrown = true;
                $this->assertCount(19, $event->getFormInputs());
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
        $this->assertCount(21, $formData['inputs']);
        $this->assertArrayHasKey('test_input', $formData['inputs']);
        $this->assertTrue($eventIsThrown);
    }
}

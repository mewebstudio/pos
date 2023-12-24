<?php
/**
 * @license MIT
 */

namespace Functional;

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\Functional\PaymentTestTrait;
use Monolog\Test\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class GarantiPosTest extends TestCase
{
    use PaymentTestTrait;

    private CreditCardInterface $card;

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

        $this->pos = PosFactory::createPosGateway($account, $config, new EventDispatcher());
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
        $statusOrder = $this->createStatusOrder($this->pos, $lastResponse);

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
        $statusOrder = $this->createCancelOrder($this->pos, $lastResponse);

        $this->pos->cancel($statusOrder);

        $this->assertTrue($this->pos->isSuccess());
        $response = $this->pos->getResponse();
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
    }
}

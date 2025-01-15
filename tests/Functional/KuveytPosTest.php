<?php

/**
 * @license MIT
 */

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\PosInterface;
use Monolog\Test\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class KuveytPosTest extends TestCase
{
    use \Mews\Pos\Tests\Functional\PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var \Mews\Pos\Gateways\KuveytPos */
    private PosInterface $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../config/pos_test.php';

        $account = \Mews\Pos\Factory\AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '496',
            'apitest',
            '400235',
            'api123',
            PosInterface::MODEL_3D_SECURE
        );

        $this->eventDispatcher = new EventDispatcher();

        $this->pos = PosFactory::createPosGateway($account, $config, $this->eventDispatcher);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '5188961939192544',
            '25',
            '06',
            '929',
            'John Doe',
            CreditCardInterface::CARD_TYPE_MASTERCARD
        );
    }

    /**
     * NOT: sadece Turkiye IPsiyle istek gonderince cevap alabiliyoruz.
     * @return void
     */
    public function testCreate3DFormData(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_SECURE);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown                  = true;
                $additionalRequestDataForKuveyt = [
                    'DeviceData'     => [
                        /**
                         * DeviceChannel : DeviceData alanı içerisinde gönderilmesi beklenen işlemin yapıldığı cihaz bilgisi.
                         * 2 karakter olmalıdır. 01-Mobil, 02-Web Browser için kullanılmalıdır.
                         */
                        'DeviceChannel' => '02',
                    ],
                    'CardHolderData' => [
                        /**
                         * BillAddrCity: Kullanılan kart ile ilişkili kart hamilinin fatura adres şehri.
                         * Maksimum 50 karakter uzunluğunda olmalıdır.
                         */
                        'BillAddrCity'     => 'İstanbul',
                        /**
                         * BillAddrCountry Kullanılan kart ile ilişkili kart hamilinin fatura adresindeki ülke kodu.
                         * Maksimum 3 karakter uzunluğunda olmalıdır.
                         * ISO 3166-1 sayısal üç haneli ülke kodu standardı kullanılmalıdır.
                         */
                        'BillAddrCountry'  => '792',
                        /**
                         * BillAddrLine1: Kullanılan kart ile ilişkili kart hamilinin teslimat adresinde yer alan sokak vb. bilgileri içeren açık adresi.
                         * Maksimum 150 karakter uzunluğunda olmalıdır.
                         */
                        'BillAddrLine1'    => 'XXX Mahallesi XXX Caddesi No 55 Daire 1',
                        /**
                         * BillAddrPostCode: Kullanılan kart ile ilişkili kart hamilinin fatura adresindeki posta kodu.
                         */
                        'BillAddrPostCode' => '34000',
                        /**
                         * BillAddrState: CardHolderData alanı içerisinde gönderilmesi beklenen ödemede kullanılan kart ile ilişkili kart hamilinin fatura adresindeki il veya eyalet bilgisi kodu.
                         * ISO 3166-2'de tanımlı olan il/eyalet kodu olmalıdır.
                         */
                        'BillAddrState'    => '40',
                        /**
                         * Email: Kullanılan kart ile ilişkili kart hamilinin iş yerinde oluşturduğu hesapta kullandığı email adresi.
                         * Maksimum 254 karakter uzunluğunda olmalıdır.
                         */
                        'Email'            => 'xxxxx@gmail.com',
                        'MobilePhone'      => [
                            /**
                             * Cc: Kullanılan kart ile ilişkili kart hamilinin cep telefonuna ait ülke kodu. 1-3 karakter uzunluğunda olmalıdır.
                             */
                            'Cc'         => '90',
                            /**
                             * Subscriber: Kullanılan kart ile ilişkili kart hamilinin cep telefonuna ait abone numarası.
                             * Maksimum 15 karakter uzunluğunda olmalıdır.
                             */
                            'Subscriber' => '1234567899',
                        ],
                    ],
                ];
                $requestData                    = $requestDataPreparedEvent->getRequestData();
                $requestData                    = array_merge($requestData, $additionalRequestDataForKuveyt);

                $requestDataPreparedEvent->setRequestData($requestData);
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
            }
        );

        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card,
        );

        $this->assertIsArray($formData);
        $this->assertNotEmpty($formData);
        $this->assertTrue($eventIsThrown);
    }

    public function testNonSecurePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(17, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $response = $this->pos->getResponse();

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $this->pos->getResponse();
    }

    /**
     * @depends testStatusSuccess
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
                $this->assertCount(14, $requestDataPreparedEvent->getRequestData());
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
                $this->assertCount(15, $requestDataPreparedEvent->getRequestData());
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

    public function testNonSecurePaymentSuccessForRefundTest(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess());

        return $this->pos->getResponse();
    }

    //    /**
    //     * @depends testNonSecurePaymentSuccessForRefundTest
    //     */
    //    public function testFullRefundFail(array $lastResponse): array
    //    {
    //        $refundOrder           = $this->createRefundOrder(\get_class($this->pos), $lastResponse);
    //
    //        $eventIsThrown = false;
    //        $this->eventDispatcher->addListener(
    //            RequestDataPreparedEvent::class,
    //            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
    //                $eventIsThrown = true;
    //                $this->assertSame(PosInterface::TX_TYPE_REFUND, $requestDataPreparedEvent->getTxType());
    //                $this->assertCount(14, $requestDataPreparedEvent->getRequestData());
    //            });
    //
    //        $this->pos->refund($refundOrder);
    //
    //        $this->assertFalse($this->pos->isSuccess());
    //        $response = $this->pos->getResponse();
    //        $this->assertIsArray($response);
    //        $this->assertNotEmpty($response);
    //        $this->assertTrue($eventIsThrown);
    //        $this->assertSame(
    //            'İade işlemi, satışla aynı gün içerisinde yapılamaz. İptal işlemi yapabilirsiniz.',
    //            $response['error_message']
    //        );
    //
    //        return $lastResponse;
    //    }

    /**
     * @depends testNonSecurePaymentSuccessForRefundTest
     */
    public function testPartialRefundSuccess(array $lastResponse): array
    {
        $refundOrder           = $this->createRefundOrder(
            \get_class($this->pos),
            $lastResponse,
            $lastResponse['amount'] - 3,
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_REFUND_PARTIAL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(14, $requestDataPreparedEvent->getRequestData());
            }
        );

        $this->pos->refund($refundOrder);
        $response = $this->pos->getResponse();

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'error');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }
}

<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\EstV3PosRequestDataMapper;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\EstV3PosRequestDataMapper
 */
class EstV3PosRequestDataMapperTest extends TestCase
{
    private EstPosAccount $account;

    private CreditCardInterface $card;

    private EstV3PosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../../config/pos_test.php';

        $this->account = AccountFactory::createEstPosAccount(
            'payten_v3_hash',
            '190100000',
            'ZIRAATAPI',
            'ZIRAAT19',
            PosInterface::MODEL_3D_SECURE,
            '123456'
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos        = PosFactory::createPosGateway($this->account, $config, $dispatcher);

        $this->crypt             = $this->createMock(CryptInterface::class);
        $this->requestDataMapper = new EstV3PosRequestDataMapper($dispatcher, $this->crypt);
        $this->card              = CreditCardFactory::createForGateway($pos, '5555444433332222', '22', '01', '123', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(
        array  $order,
        string $gatewayURL,
        string $txType,
        string $paymentModel,
        bool   $isWithCard,
        array  $expected
    ): void
    {
        $this->crypt->expects(self::once())
            ->method('create3DHash')
            ->willReturn($expected['inputs']['hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['inputs']['rnd']);

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayURL,
            $isWithCard ? $this->card : null
        );

        $this->assertEquals($expected, $actual);
    }

    public static function threeDFormDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];

        return [
            'without_card' => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'clientid'      => '190100000',
                        'storetype'     => '3d',
                        'amount'        => '100.25',
                        'oid'           => 'order222',
                        'okUrl'         => 'https://domain.com/success',
                        'failUrl'       => 'https://domain.com/fail_url',
                        'rnd'           => 'rand-21212',
                        'lang'          => 'tr',
                        'currency'      => '949',
                        'taksit'        => '',
                        'TranType'      => 'Auth',
                        'hashAlgorithm' => 'ver3',
                        'hash'          => '7tt3i3SMhzR3jYjCMwNrolSn7ksY7eKz2kVsqt/nUGK6XNw9/dMMZPVqHK9pQROGEIW3PJWut6v1Xv6ZDtnuSA==',
                    ],
                ],
            ],
            'with_card'    => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => true,
                'expected'     => [
                    'gateway' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'clientid'                        => '190100000',
                        'storetype'                       => '3d',
                        'amount'                          => '100.25',
                        'oid'                             => 'order222',
                        'okUrl'                           => 'https://domain.com/success',
                        'failUrl'                         => 'https://domain.com/fail_url',
                        'rnd'                             => 'rand-21212',
                        'lang'                            => 'tr',
                        'currency'                        => '949',
                        'taksit'                          => '',
                        'TranType'                        => 'Auth',
                        'hashAlgorithm'                   => 'ver3',
                        'hash'                            => '3fvBzh0HT3UiKUTXis0Ke2NG3mAp9eBOwx26bstv+l6L946GrOF2JklXfqTNc6VBeqUSkuLxo4ErtwCWuPCzYw==',
                        'pan'                             => '5555444433332222',
                        'Ecom_Payment_Card_ExpDate_Month' => '01',
                        'Ecom_Payment_Card_ExpDate_Year'  => '22',
                        'cv2'                             => '123',
                    ],
                ],
            ],
            '3d_host'      => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'clientid'      => '190100000',
                        'storetype'     => '3d_host',
                        'amount'        => '100.25',
                        'oid'           => 'order222',
                        'okUrl'         => 'https://domain.com/success',
                        'failUrl'       => 'https://domain.com/fail_url',
                        'rnd'           => 'rand-21212',
                        'lang'          => 'tr',
                        'currency'      => '949',
                        'taksit'        => '',
                        'TranType'      => 'Auth',
                        'hashAlgorithm' => 'ver3',
                        'hash'          => 'wlqP71Pwu5+zaCYCGxWpbqf1cAsbou5p5PDAds4YcejFO5AVTw0PjnzNiFnYX900ZL38rQw8Jt/YhMmZ5bJ/qA==',
                    ],
                ],
            ],
        ];
    }
}

<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\DataMapper\RequestDataMapper;

use Mews\Pos\DataMapper\RequestDataMapper\EstV3PosRequestDataMapper;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\EstV3PosRequestDataMapper
 */
class EstV3PosRequestDataMapperTest extends TestCase
{
    private EstPosAccount $account;

    private CreditCardInterface $card;

    private EstV3PosRequestDataMapper $requestDataMapper;

    private array $order;

    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../../config/pos_test.php';

        $this->account = AccountFactory::createEstPosAccount(
            'akbankv3',
            '190100000',
            'ZIRAATAPI',
            'ZIRAAT19',
            PosInterface::MODEL_3D_SECURE,
            '123456'
        );

        $this->order = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'rand'        => 'rand-21212',
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos        = PosFactory::createPosGateway($this->account, $this->config, $dispatcher);

        $crypt                   = CryptFactory::createGatewayCrypt(EstV3Pos::class, new NullLogger());
        $this->requestDataMapper = new EstV3PosRequestDataMapper($dispatcher, $crypt);
        $this->card              = CreditCardFactory::create($pos, '5555444433332222', '22', '01', '123', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
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
    ) {
        $account = $this->account;
        $card    = $isWithCard ? $this->card : null;

        $actual = $this->requestDataMapper->create3DFormData(
            $account,
            $order,
            $paymentModel,
            $txType,
            $gatewayURL,
            $card
        );

        $this->assertEquals($expected, $actual);
    }


    /**
     * @return void
     */
    public function testGet3DHostFormData()
    {
        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d'];

        $inputs = [
            'clientid'      => $this->account->getClientId(),
            'storetype'     => '3d_host',
            'amount'        => $this->order['amount'],
            'oid'           => $this->order['id'],
            'okUrl'         => $this->order['success_url'],
            'failUrl'       => $this->order['fail_url'],
            'callbackUrl'   => $this->order['fail_url'],
            'rnd'           => $this->order['rand'],
            'hashAlgorithm' => 'ver3',
            'lang'          => 'tr',
            'currency'      => '949',
            'TranType'      => 'Auth',
            'taksit'        => '',
        ];
        $form   = [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];

        $form['inputs']['hash'] = 'TLRkwt3AHJCyfZvjXXoG1Z56sWGVKMk7cKQqNQAGaYx8y16SOKR6SAnznRlgC3vaazQ+VtqO7PprqcOds2wN1w==';

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->account,
            $this->order,
            PosInterface::MODEL_3D_HOST,
            PosInterface::TX_PAY,
            $gatewayURL
        ));
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
            'lang'        => 'tr',
            'rand'        => 'rand-21212',
        ];

        return [
            'without_card' => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_PAY,
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
                        'callbackUrl'   => 'https://domain.com/fail_url',
                        'rnd'           => 'rand-21212',
                        'lang'          => 'tr',
                        'currency'      => '949',
                        'taksit'        => '',
                        'TranType'      => 'Auth',
                        'hashAlgorithm' => 'ver3',
                        'hash'          => 'nRzSpmIHxn9KvyOl8uJDIicDMemBiOcfQgmPxe3KvCT4Z4fnbOle+FgSx8lanbUQHOIAGQ+6bApDRc6e+tZcMw==',
                    ],
                ],
            ],
            'with_card'    => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_PAY,
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
                        'callbackUrl'                     => 'https://domain.com/fail_url',
                        'rnd'                             => 'rand-21212',
                        'lang'                            => 'tr',
                        'currency'                        => '949',
                        'taksit'                          => '',
                        'TranType'                        => 'Auth',
                        'hashAlgorithm'                   => 'ver3',
                        'hash'                            => 'ZXFP3/lcGmROjZL0q8cWbTNfFp07SewcHS1CUWu2sU0seQW0oWQcnwNOzQRDjm22KpA8UpdnyISzESu7Frd8XA==',
                        'cardType'                        => '1',
                        'pan'                             => '5555444433332222',
                        'Ecom_Payment_Card_ExpDate_Month' => '01',
                        'Ecom_Payment_Card_ExpDate_Year'  => '22',
                        'cv2'                             => '123',
                    ],
                ],
            ],
            '3d_host'    => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_PAY,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'clientid'                        => '190100000',
                        'storetype'                       => '3d_host',
                        'amount'                          => '100.25',
                        'oid'                             => 'order222',
                        'okUrl'                           => 'https://domain.com/success',
                        'failUrl'                         => 'https://domain.com/fail_url',
                        'callbackUrl'                     => 'https://domain.com/fail_url',
                        'rnd'                             => 'rand-21212',
                        'lang'                            => 'tr',
                        'currency'                        => '949',
                        'taksit'                          => '',
                        'TranType'                        => 'Auth',
                        'hashAlgorithm'                   => 'ver3',
                        'hash'                            => 'TLRkwt3AHJCyfZvjXXoG1Z56sWGVKMk7cKQqNQAGaYx8y16SOKR6SAnznRlgC3vaazQ+VtqO7PprqcOds2wN1w==',
                    ],
                ],
            ],
        ];
    }
}

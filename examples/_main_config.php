<?php

use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\Session\Session;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$root = realpath(__DIR__);
require_once "$root/../vendor/autoload.php";

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
    'cookie_samesite' => 'None',
    'cookie_secure' => true,
]);
$session        = new Session($sessionHandler);
$session->start();

$hostUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')."://$_SERVER[HTTP_HOST]";
$subMenu = [];

$handler = new \Monolog\Handler\StreamHandler(__DIR__.'/../var/log/pos.log', \Psr\Log\LogLevel::DEBUG);
$logger = new \Monolog\Logger('pos', [$handler]);

$eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

$installments = [
    0  => 'Peşin',
    2  => '2 Taksit',
    3  => '3 Taksit',
    6  => '6 Taksit',
    12 => '12 Taksit',
];

function doPayment(PosInterface $pos, string $paymentModel, string $transaction, array $order, ?\Mews\Pos\Entity\Card\CreditCardInterface $card)
{
    if (!$pos::isSupportedTransaction($transaction, $paymentModel)) {
        throw new \LogicException(
            sprintf('"%s %s" işlemi %s tarafından desteklenmiyor', $transaction, $paymentModel, get_class($pos))
        );
    }
    if (get_class($pos) === \Mews\Pos\Gateways\PayFlexV4Pos::class
        && in_array($transaction, [PosInterface::TX_PAY, PosInterface::TX_PRE_PAY], true)
        && PosInterface::MODEL_3D_SECURE === $paymentModel
    ) {
        /**
         * diger banklaradan farkli olarak 3d islemler icin de PayFlex bu asamada kredi kart bilgileri istiyor
         */
        $pos->payment($paymentModel, $order, $transaction, $card);

    } elseif ($paymentModel === PosInterface::MODEL_NON_SECURE
        && in_array($transaction, [PosInterface::TX_PAY, PosInterface::TX_PRE_PAY], true)
    ) {
        // bu asamada $card regular/non secure odemede lazim.
        $pos->payment($paymentModel, $order, $transaction, $card);
    } else {
        $pos->payment($paymentModel, $order, $transaction);
    }
}


function getGateway(\Mews\Pos\Entity\Account\AbstractPosAccount $account, \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher): ?PosInterface
{
    try {
/*        $client = new HttpClient(
            new \Http\Client\Curl\Client(),
            new \Slim\Psr7\Factory\RequestFactory(),
            new \Slim\Psr7\Factory\StreamFactory()
        );*/
        $config = require __DIR__.'/../config/pos_test.php';
        global $logger;

        $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, null, null, $logger);
        $pos->setTestMode(true);

        return $pos;
    } catch (Exception $e) {
        dd($e);
    }
}

function createCard(PosInterface $pos, array $card): \Mews\Pos\Entity\Card\CreditCardInterface
{
    try {
        return \Mews\Pos\Factory\CreditCardFactory::create(
            $pos,
            $card['number'],
            $card['year'],
            $card['month'],
            $card['cvv'],
            $card['name'],
            $card['type'] ?? null
        );
    } catch (Exception $e) {
        dd($e);
    }
}

function getNewOrder(
    string $baseUrl,
    string $ip,
    string $currency = PosInterface::CURRENCY_TRY,
    ?int $installment = 0,
    bool $tekrarlanan = false,
    ?string $lang = null
): array {

    $successUrl = $baseUrl.'response.php';
    $failUrl = $baseUrl.'response.php';

    $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));

    $order = [
        'id'          => $orderId,
        'amount'      => 1.01,
        'currency'    => $currency,
        'installment' => $installment,
        'ip'          => filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ? $ip : '127.0.0.1',

        // 3d, 3d_pay, 3d_host odemeler icin zorunlu
        'success_url' => $successUrl, // https://example.com/payment
        'fail_url'    => $failUrl, // https://example.com/payment

        // gateway'e gore zorunlu olan degerler
        'email'       => 'mail@customer.com', // Garanti
        'rand'        => md5(uniqid(time())), // EstPos, Garanti, PayFor, InterPos, VakifBank
    ];

    if ($lang) {
        //lang degeri verilmezse account (EstPosAccount) dili kullanilacak
        $order['lang'] = $lang;
    }

    if ($tekrarlanan) {
        // Desteleyen Gatewayler: GarantiPos, EstPos, PayFlexV4

        $order['installment'] = 0; // Tekrarlayan ödemeler taksitli olamaz.

        $recurringFrequency     = 3;
        $recurringFrequencyType = 'MONTH'; // DAY|WEEK|MONTH|YEAR
        $endPeriod              = $installment * $recurringFrequency;

        $order['recurring'] = [
            'frequency'     => $recurringFrequency,
            'frequencyType' => $recurringFrequencyType,
            'installment'   => $installment,
            'startDate'     => new \DateTimeImmutable(), // GarantiPos optional
            'endDate'       => (new DateTime())->modify("+$endPeriod $recurringFrequencyType"), // Sadece PayFlexV4'te zorunlu
        ];
    }

    return $order;
}

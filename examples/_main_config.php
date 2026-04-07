<?php

use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\PosInterface;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$root = realpath(__DIR__);
require_once "$root/../vendor/autoload.php";

$ip = getUserIp();

// Configure session with security options
session_set_cookie_params([
    'samesite' => 'None',
    'secure'   => true,
    'httponly' => true, // Javascriptin session'a erişimini engelliyoruz.
]);
session_start();

$hostUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')."://$_SERVER[HTTP_HOST]";
$subMenu = [];

$handler = new \Monolog\Handler\StreamHandler(__DIR__.'/../var/log/pos.log', \Psr\Log\LogLevel::DEBUG);
$logger  = new \Monolog\Logger('pos', [$handler]);

$eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

$installments = [
    0  => 'Peşin',
    2  => '2 Taksit',
    3  => '3 Taksit',
    6  => '6 Taksit',
    12 => '12 Taksit',
];

$paymentModel  = null;
$posClass      = null;
$transaction   = null;

function doPayment(PosInterface $pos, string $paymentModel, string $transaction, array $order, ?\Mews\Pos\Entity\Card\CreditCardInterface $card)
{
    if (!$pos::isSupportedTransaction($transaction, $paymentModel)) {
        throw new \LogicException(
            sprintf('"%s %s" işlemi %s tarafından desteklenmiyor', $transaction, $paymentModel, get_class($pos))
        );
    }

    if (PosInterface::MODEL_NON_SECURE === $paymentModel) {
        if (in_array($transaction, [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::TX_TYPE_PAY_PRE_AUTH], true)) {
            // bu işlemlerde kredi kart bilgileri zorunlu.
            $pos->payment($paymentModel, $order, $transaction, $card);
        } elseif (PosInterface::TX_TYPE_PAY_POST_AUTH === $transaction) {
            $pos->payment($paymentModel, $order, $transaction);
        }

        return;
    }

    if (PosInterface::MODEL_3D_SECURE === $paymentModel) {
        if (!in_array($transaction, [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::TX_TYPE_PAY_PRE_AUTH], true)) {
            throw new \LogicException('Hatalı işlem');
        }
        $gatewayResponseData = $_POST; // 3D otorizasyon sonrası bankadan gelen yanıt verileri.
        if (get_class($pos) === \Mews\Pos\Gateways\PayFlexCPV4Pos::class) {
            $gatewayResponseData = $_GET;
        }
        if (get_class($pos) === \Mews\Pos\Gateways\PayFlexV4Pos::class) {
            /**
             * diğer banklaradan farklı olarak 3d işlemler için de PayFlex bu aşamada kredi kart bilgileri istiyor.
             */
            $pos->payment($paymentModel, $order, $transaction, $card, $gatewayResponseData);

            return;
        }

        $pos->payment($paymentModel, $order, $transaction, null, $gatewayResponseData);
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

        $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, $config, $eventDispatcher, null, $logger);

        return $pos;
    } catch (Exception $e) {
        dd($e);
    }
}

function createCard(PosInterface $pos, array $card): \Mews\Pos\Entity\Card\CreditCardInterface
{
    try {
        return \Mews\Pos\Factory\CreditCardFactory::createForGateway(
            $pos,
            $card['number'],
            $card['year'],
            $card['month'],
            $card['cvv'],
            $card['name'],
            $card['type'] ?? null
        );
    } catch (\Mews\Pos\Exceptions\CardTypeRequiredException $e) {
        // bu gateway için kart tipi zorunlu
        dd($e);
    } catch (\Mews\Pos\Exceptions\CardTypeNotSupportedException $e) {
        // sağlanan kart tipi bu gateway tarafından desteklenmiyor
        dd($e);
    } catch (\Exception $e) {
        dd($e);
    }
}

function createPaymentOrder(
    PosInterface $pos,
    string $paymentModel,
    string $baseUrl,
    string $ip,
    string $currency = PosInterface::CURRENCY_TRY,
    ?int $installment = 0,
    bool $tekrarlanan = false,
    ?string $lang = null
): array {
    if ($tekrarlanan && get_class($pos) === AkbankPos::class) {
        // AkbankPos'ta recurring odemede orderTrackId/orderId en az 36 karakter olmasi gerekiyor
        $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 28));
    } else {
        $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));
    }

    $order = [
        'id'          => $orderId,
        'amount'      => 10.01,
        'currency'    => $currency,
        'installment' => $installment,
        'ip'          => filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ? $ip : '127.0.0.1',
    ];

    if ($pos instanceof \Mews\Pos\Gateways\ParamPos
        || in_array($paymentModel, [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_3D_HOST,
            PosInterface::MODEL_3D_PAY_HOSTING,
        ], true)) {
        $order['success_url'] = $baseUrl.'response.php';
        $order['fail_url']    = $baseUrl.'response.php';
    }

    if ($lang) {
        //lang degeri verilmezse account (EstPosAccount) dili kullanilacak
        $order['lang'] = $lang;
    }

    if ($tekrarlanan) {
        // Desteleyen Gatewayler: GarantiPos, EstPos, PayFlexV4, AkbankPos

        $order['installment'] = 0; // Tekrarlayan ödemeler taksitli olamaz.

        $recurringFrequency     = 3;
        $recurringFrequencyType = 'MONTH'; // DAY|WEEK|MONTH|YEAR
        $endPeriod              = $installment * $recurringFrequency;

        $order['recurring'] = [
            'frequency'     => $recurringFrequency,
            'frequencyType' => $recurringFrequencyType,
            'installment'   => $installment,
            'startDate'     => new \DateTimeImmutable(), // GarantiPos|PayFlexV4 optional
            'endDate'       => (new DateTime())->modify("+$endPeriod $recurringFrequencyType"), // Sadece PayFlexV4'te zorunlu
        ];
    }

    return $order;
}

function getUserIp(): string
{
    // Get client IP address
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    // Validate IP
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ip = '127.0.0.1';
    }

    return $ip;
}

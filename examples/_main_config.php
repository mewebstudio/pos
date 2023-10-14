<?php
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
$session        = new \Symfony\Component\HttpFoundation\Session\Session($sessionHandler);
$session->start();

$hostUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')."://$_SERVER[HTTP_HOST]";
$subMenu = [];

$eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

function getGateway(\Mews\Pos\Entity\Account\AbstractPosAccount $account, \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher): ?\Mews\Pos\PosInterface
{
    try {
        $handler = new \Monolog\Handler\StreamHandler(__DIR__.'/../var/log/pos.log', \Psr\Log\LogLevel::DEBUG);
        $logger = new \Monolog\Logger('pos', [$handler]);

/*        $client = new HttpClient(
            new \Http\Client\Curl\Client(),
            new \Slim\Psr7\Factory\RequestFactory(),
            new \Slim\Psr7\Factory\StreamFactory()
        );*/
        $config = require __DIR__.'/../config/pos_test.php';
        $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, $config, $eventDispatcher, null, $logger);
        $pos->setTestMode(true);

        return $pos;
    } catch (Exception $e) {
        dd($e);
    }
}

function createCard(\Mews\Pos\PosInterface $pos, array $card): \Mews\Pos\Entity\Card\AbstractCreditCard
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

function createNewPaymentOrderCommon(
    string $baseUrl,
    string $ip,
    string $currency = PosInterface::CURRENCY_TRY,
    ?int $installment = 0,
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

        //3d, 3d_pay, 3d_host odemeler icin zorunlu
        'success_url' => $successUrl, // https://example.com/payment
        'fail_url'    => $failUrl, // https://example.com/payment

        //gateway'e gore zorunlu olan degerler
        'ip'          => filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ? $ip : '127.0.0.1', //EstPos, Garanti, KuveytPos, VakifBank
        'email'       => 'mail@customer.com', // Garanti
        'rand'        => md5(uniqid(time())), //EstPos, Garanti, PayFor, InterPos, VakifBank
    ];

    if ($lang) {
        //lang degeri verilmezse account (EstPosAccount) dili kullanilacak
        $order['lang'] = $lang;
    }

    return $order;
}

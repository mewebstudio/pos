<?php

require '../../../vendor/autoload.php';

$host_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]";
$path = '/pos/examples/akbank/3d-pay/';
$base_url = $host_url . $path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$account = [
    'bank'          => 'akbank',
    'model'         => '3d_pay',
    'client_id'     => 'xxx',
    'store_key'     => 'xxx',
    'env'           => 'test',
];

try {
    $pos = new \Mews\Pos\Pos($account);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    var_dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    var_dump($e->getCode(), $e->getMessage());
}

$amount = (double) 100;
$instalment = '0';

$ok_url = $base_url . 'response.php';
$fail_url = $base_url . 'response.php';

$transaction = 'pay'; // pay => Auth, pre PreAuth
$transaction_type = $pos->bank->types[$transaction];

$rand = microtime();

$order = [
    'id'                => 'unique-order-id-006',
    'email'             => 'mail@customer.com', // optional
    'name'              => 'John Doe', // optional
    'amount'            => $amount,
    'installment'       => $instalment,
    'currency'          => 'TRY',
    'ip'                => $ip,
    'ok_url'            => $ok_url,
    'fail_url'          => $fail_url,
    'transaction'       => $transaction,
    'transaction_type'  => $transaction_type,
    'lang'              => 'tr',
    'rand'              => $rand,
];

$pos->prepare($order);

$hash = $pos->bank->create3DHash();
$order['hash'] = $hash;

$currency = $pos->config['currencies'][$order['currency']];
$gateway = $pos->bank->gateway;

$template_title = '3D Pay Model Payment';

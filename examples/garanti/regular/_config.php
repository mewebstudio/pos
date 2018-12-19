<?php

require '../../../vendor/autoload.php';

$host_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]";
$path = '/pos/examples/garanti/regular/';
$base_url = $host_url . $path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$account = [
    'bank'              => 'garanti',
    'model'             => 'regular',
    'client_id'         => '7000679',
    'terminal_id'       => '30691298',
    'username'          => 'PROVAUT',
    'password'          => '123qweASD/',
    'refund_username'   => 'PROVRFN',
    'refund_password'   => '123qweASD/',
    'env'               => 'test',
];

try {
    $pos = new \Mews\Pos\Pos($account);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    var_dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    var_dump($e->getCode(), $e->getMessage());
}

$gateway = $base_url . 'response.php';

$template_title = 'Regular Payment';

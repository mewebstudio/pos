<?php

session_start();

require '../../../vendor/autoload.php';

$host_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]";
$path = '/pos/examples/ykb/3d/';
$base_url = $host_url . $path;

$success_url = $base_url . 'response.php';
$fail_url = $base_url . 'response.php';

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$account = [
    'bank'              => 'yapikredi',
    'model'             => '3d',
    'client_id'         => 'XXXXXX',
    'terminal_id'       => 'XXXXXX',
    'posnet_id'         => 'XXXXXX',
    'username'          => 'XXXXXX',
    'password'          => 'XXXXXX',
    'store_key'         => '10,10,10,10,10,10,10,10',
    'promotion_code'    => '',
    'env'               => 'test',
];

try {
    $pos = new \Mews\Pos\Pos($account);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    var_dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    var_dump($e->getCode(), $e->getMessage());
}

$template_title = '3D Model Payment';

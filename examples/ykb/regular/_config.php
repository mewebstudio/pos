<?php

require '../../_main_config.php';

$path = '/pos/examples/ykb/regular/';
$baseUrl = $hostUrl . $path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$account = [
    'bank'          => 'yapikredi',
    'model'         => 'regular',
    'client_id'     => '6706598320',
    'terminal_id'   => '67322946',
    'posnet_id'     => '27426',
    'env'           => 'test',
];

try {
    $pos = new \Mews\Pos\Pos($account);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$gateway = $baseUrl . 'response.php';

$templateTitle = 'Regular Payment';

<?php

require '_config.php';

$order_id = date('Ymd') . strtoupper(substr(uniqid(sha1(time())),0,4));
$amount = (double) 100;

$order = [
    'id'            => $order_id,
    'name'          => 'John Doe', // optional
    'email'         => 'mail@customer.com', // optional
    'user_id'       => '12', // optional
    'amount'        => $amount,
    'installment'   => '0',
    'currency'      => 'TRY',
    'ip'            => $ip,
    'transaction'   => 'pay', // pay => Auth, pre PreAuth
];

$card = [
    'number'        => '4355084355084358',
    'month'         => '12',
    'year'          => '18',
    'cvv'           => '000',
];

try {
    $pos->prepare($order);
} catch (\Mews\Pos\Exceptions\UnsupportedTransactionTypeException $e) {
    var_dump($e->getCode(), $e->getMessage());
}

$payment = $pos->payment($card);

var_dump($payment->response);

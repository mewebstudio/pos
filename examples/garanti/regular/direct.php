<?php

require '_config.php';

$orderId = date('Ymd') . strtoupper(substr(uniqid(sha1(time())),0,4));
$amount = (double) 100;

$order = [
    'id'            => $orderId,
    'name'          => 'John Doe', // optional
    'email'         => 'mail@customer.com', // optional
    'user_id'       => '12', // optional
    'amount'        => $amount,
    'installment'   => '0',
    'currency'      => 'TRY',
    'ip'            => $ip,
    'transaction'   => 'pay', // pay => Auth, pre PreAuth
];

try {
    $pos->prepare($order);
} catch (\Mews\Pos\Exceptions\UnsupportedTransactionTypeException $e) {
    dump($e->getCode(), $e->getMessage());
}

$card = new \Mews\Pos\Entity\Card\CreditCardGarantiPos('4282209027132016', '20', '05', '165');
$payment = $pos->payment($card);

dump($payment->getResponse());

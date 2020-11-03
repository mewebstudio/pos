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

$card = new \Mews\Pos\Entity\Card\CreditCardPosNet('4355084355084358', '18', '12', '000');

try {
    $pos->prepare($order);
} catch (\Mews\Pos\Exceptions\UnsupportedTransactionTypeException $e) {
    dump($e->getCode(), $e->getMessage());
}

$payment = $pos->payment($card);

dump($payment->getResponse());

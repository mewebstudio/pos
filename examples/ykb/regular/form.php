<?php

require '_config.php';

$order = getNewOrder($baseUrl, $request->get('installment'));
$session->set('order', $order);
$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$card = new \Mews\Pos\Entity\Card\CreditCardPosNet(
    $request->get('number'),
    $request->get('year'),
    $request->get('month'),
    $request->get('cvv'),
    $request->get('name'),
    $request->get('type')
);

require '../_payment_response.php';

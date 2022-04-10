<?php

use Mews\Pos\Entity\Card\CreditCardGarantiPos;

require_once '_config.php';

$order = getNewOrder($baseUrl, $ip, $request->get('installment'));
$session->set('order', $order);
$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$card = new CreditCardGarantiPos(
    $request->get('number'),
    $request->get('year'),
    $request->get('month'),
    $request->get('cvv')
);

require '../_payment_response.php';

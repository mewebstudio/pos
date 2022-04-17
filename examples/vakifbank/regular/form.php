<?php

require_once '_config.php';

$order = getNewOrder($baseUrl, $ip, $session, $request->get('installment'));
$session->set('order', $order);
$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$card = createCard($pos, $request->request->all());

require '../../template/_payment_response.php';

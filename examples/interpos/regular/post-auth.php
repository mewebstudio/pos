<?php

require_once '_config.php';

$order = getNewOrder($baseUrl);
$session->set('order', $order);
$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_POST_PAY;

require '../_payment_response.php';

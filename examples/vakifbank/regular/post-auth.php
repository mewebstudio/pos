<?php

require '_config.php';

$templateTitle = 'Post Auth Order (ön provizyonu tamamlama)';

$order = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    'id'       => $order['id'],
    'amount'   => $order['amount'],
    'currency' => $order['currency'],
    'ip'       => $order['ip'],
];

$session->set('post_order', $order);
$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_POST_PAY;
$card = null;

require '../../template/_payment_response.php';

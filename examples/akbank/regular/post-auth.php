<?php

require '_config.php';

$templateTitle = 'Post Auth Order (ön provizyonu tamamlama)';

$ord = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    'id' => $ord['id'],
];

$session->set('post_order', $order);
$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_POST_PAY;
$card = null;

require '../../template/_payment_response.php';

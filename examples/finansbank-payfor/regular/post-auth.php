<?php

require '_config.php';

$templateTitle = 'Post Auth Order (ön provizyonu tamamlama)';

$order = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip);

$session->set('post_order', $order);
$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_POST_PAY;
$card = null;

require '../../template/_payment_response.php';

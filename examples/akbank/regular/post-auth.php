<?php

use Mews\Pos\PosInterface;

require '_config.php';

$templateTitle = 'Post Auth Order (ön provizyonu tamamlama)';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', PosInterface::CURRENCY_TRY));

$order = [
    'id' => $ord['id'],
];

$session->set('post_order', $order);
$transaction = PosInterface::TX_POST_PAY;
$card = null;

require '../../_templates/_payment_response.php';

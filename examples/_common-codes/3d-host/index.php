<?php

require '_config.php';
require '../../template/_header.php';

$order = getNewOrder(
    $baseUrl,
    $ip,
    $request->get('currency', 'TRY'),
    $session,
    $request->get('installment'),
    false,
    $request->get('lang', \Mews\Pos\Gateways\AbstractGateway::LANG_TR)
);
$session->set('order', $order);

$pos->prepare($order, $transaction);

$formData = $pos->get3DFormData();

require '../../template/_redirect_form.php';
require '../../template/_footer.php';

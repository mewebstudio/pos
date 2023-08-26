<?php

require '_config.php';
require '../../_templates/_header.php';

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

$formData = $pos->get3DFormData(\Mews\Pos\Gateways\AbstractGateway::MODEL_3D_HOST);

require '../../_templates/_redirect_form.php';
require '../../_templates/_footer.php';

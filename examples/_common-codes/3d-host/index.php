<?php

use Mews\Pos\PosInterface;

require '_config.php';
require '../../_templates/_header.php';

$order = getNewOrder(
    $baseUrl,
    $ip,
    $request->get('currency', PosInterface::CURRENCY_TRY),
    $session,
    $request->get('installment'),
    false,
    $request->get('lang', PosInterface::LANG_TR)
);
$session->set('order', $order);

$formData = $pos->get3DFormData($order, PosInterface::MODEL_3D_HOST, $transaction);

require '../../_templates/_redirect_form.php';
require '../../_templates/_footer.php';

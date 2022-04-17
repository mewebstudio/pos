<?php

use Symfony\Component\HttpFoundation\RedirectResponse;

require '_config.php';
require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new RedirectResponse($baseUrl.'index.php');
    exit();
}

$order = getNewOrder($baseUrl, $request->get('installment'));
$session->set('order', $order);

$card = createCard($pos, $request->request->all());
$pos->prepare($order, $transaction, $card);

$formData = $pos->get3DFormData();

require '../../template/_redirect_form.php';
require '../../template/_footer.php';

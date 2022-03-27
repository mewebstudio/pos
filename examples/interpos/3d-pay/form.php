<?php

use Mews\Pos\Entity\Card\CreditCardInterPos;
use Symfony\Component\HttpFoundation\RedirectResponse;

require '_config.php';

require '../../template/_header.php';
require '../_header.php';

if ($request->getMethod() !== 'POST') {
    echo new RedirectResponse($baseUrl);
    exit();
}

$order = getNewOrder($baseUrl, $request->get('installment'));
$session->set('order', $order);

$card = new CreditCardInterPos(
    $request->get('number'),
    $request->get('year'),
    $request->get('month'),
    $request->get('cvv'),
    $request->get('name'),
    $request->get('type')
);

$pos->prepare($order, $transaction, $card);

$formData = $pos->get3DFormData();

require '../_redirect_form.php';

require '../../template/_footer.php';

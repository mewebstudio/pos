<?php

use Symfony\Component\HttpFoundation\RedirectResponse;

require '_config.php';

require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new RedirectResponse($baseUrl);
    exit();
}

$order = getNewOrder($baseUrl, $ip, $request->get('installment'));

$session->set('order', $order);

$card = new \Mews\Pos\Entity\Card\CreditCardKuveytPos(
    $request->get('number'),
    $request->get('year'),
    $request->get('month'),
    $request->get('cvv'),
    $request->get('name'),
    $request->get('type')
);

$pos->prepare($order, $transaction, $card);

try {
    $formData = $pos->get3DFormData();
} catch (\Exception $e) {
    dd($e);
}

require '../../template/_redirect_form.php';
require '../../template/_footer.php';

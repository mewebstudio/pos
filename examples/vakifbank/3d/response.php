<?php

require './_config.php';

$savedCard = $session->get('card');

$card = new \Mews\Pos\Entity\Card\CreditCardVakifBank(
    $savedCard['number'],
    $savedCard['year'],
    $savedCard['month'],
    $savedCard['cvv'],
    $savedCard['name'],
    $savedCard['type']
);

require '../../template/_payment_response.php';

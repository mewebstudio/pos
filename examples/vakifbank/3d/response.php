<?php

require './_config.php';

$savedCard = $session->get('card');
$card = createCard($pos, $savedCard);

require '../../template/_payment_response.php';

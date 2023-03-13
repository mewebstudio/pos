<?php

require './_config.php';

$savedCard = $session->get('card');
$card = createCard($pos, $savedCard);

require '../../_templates/_payment_response.php';

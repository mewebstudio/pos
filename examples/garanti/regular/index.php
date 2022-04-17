<?php

require '_config.php';
require '../../template/_header.php';

$url = $baseUrl.'form.php';
$card = createCard($pos, $testCards['visa1']);

require '../../template/_credit_card_form.php';
require '../../template/_footer.php';

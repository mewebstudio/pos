<?php

require '_config.php';
require '../../template/_header.php';
require '../_header.php';

$url = $baseUrl.'form.php';
$card = $testCards['visa1'];

require '../_credit_card_form.php';
require '../../template/_footer.php';

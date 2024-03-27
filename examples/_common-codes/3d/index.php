<?php

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/3d/_config.php
require '_config.php';

require '../../_templates/_header.php';

$url = $baseUrl.'form.php';
$card = createCard($pos, $testCards['visa1']);

require '../../_templates/_credit_card_form.php';
require '../../_templates/_footer.php';

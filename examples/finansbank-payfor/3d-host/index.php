<?php

require '_config.php';
require '../../template/_header.php';
require '../_header.php';

$order = getNewOrder($baseUrl, $ip);
$session->set('order', $order);

$pos->prepare($order, $transaction);

$formData = $pos->get3DFormData();

require '../_redirect_form.php';
require '../../template/_footer.php';

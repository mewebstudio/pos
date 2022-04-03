<?php

require '../_payment_config.php';

$baseUrl = $hostUrl.'/interpos/regular/';

$userCode =  'InterTestApi';
$userPass = '3';
$shopCode = '3123';
//$merchantPass non secure islemler icin kullanilmiyor
$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount('denizbank', $shopCode, $userCode, $userPass);

$pos = getGateway($account);

$templateTitle = 'Regular Payment';

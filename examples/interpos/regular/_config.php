<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$userCode =  'InterTestApi';
$userPass = '3';
$shopCode = '3123';
//$merchantPass non secure islemler icin kullanilmiyor
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount('denizbank', $shopCode, $userCode, $userPass);

$pos = getGateway($account);

$templateTitle = 'Regular Payment';

<?php

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AbstractGateway;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createPosNetAccount(
    'albaraka',
    '6702640212',
    '',
    '',
    '67C16990',
    '1010062861356072',
    AbstractGateway::MODEL_3D_SECURE,
    '10,10,10,10,10,10,10,10'
);

/**
 * vftCode: Vade Farklı işlemler için kullanılacak olan kampanya kodunu belirler.
 * Üye İşyeri için tanımlı olan kampanya kodu, İşyeri Yönetici Ekranlarına giriş
 * yapıldıktan sonra, Üye İşyeri bilgileri sayfasından öğrenilebilinir.
 * vtfCode set etmek icin simdilik bu sekilde:
 * $account->promotion_code = 'xxx';
 *
 * ilerde vtfCode atanmasi duzgun ele alinacak
 */

$pos = getGateway($account);

$transaction = $session->get('tx', AbstractGateway::TX_PAY);

$templateTitle = '3D Model Payment';

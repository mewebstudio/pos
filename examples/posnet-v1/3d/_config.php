<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createPosNetAccount(
    'albaraka',
    '6702640212', // 10 haneli üye işyeri numarası
    '67C16990', // 8 haneli üye işyeri terminal numarası
    '1010062861356072', // 16 haneli üye işyeri EPOS numarası.
    PosInterface::MODEL_3D_SECURE,
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

$transaction = $session->get('tx', PosInterface::TX_PAY);

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;

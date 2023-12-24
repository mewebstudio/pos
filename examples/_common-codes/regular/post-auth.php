<?php

use Mews\Pos\PosInterface;

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require '_config.php';

$templateTitle = 'Post Auth Order (ön provizyonu tamamlama)';

function createPostPayOrder(PosInterface $pos, \Symfony\Component\HttpFoundation\Session\SessionInterface $session, string $ip): array
{
    // PRE_PAY işlem sonucunda dönen $pos->getResponse() verisi
    $lastResponse = $session->get('last_response');

    if (!$lastResponse) {
        throw new \LogicException('ödeme verisi bulunamadı, önce PRE_PAY ödemesi yapınız');
    }

    $postAuth = [
        'id'          => $lastResponse['order_id'],
        'amount'      => $lastResponse['amount'],
        'currency'    => $lastResponse['currency'],
        'ip'          => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (get_class($pos) === \Mews\Pos\Gateways\PosNetV1Pos::class || get_class($pos) === \Mews\Pos\Gateways\PosNet::class) {
        $postAuth['installment'] = $lastResponse['installment'];
        $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
    }

    return $postAuth;
}

$order = createPostPayOrder($pos, $session, $ip);
dump($order);


$session->set('post_order', $order);
$transaction = PosInterface::TX_TYPE_POST_PAY;
$card = null;

require '../../_templates/_payment_response.php';

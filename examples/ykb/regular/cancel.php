<?php

require '_config.php';
$templateTitle = 'Cancel Order';
require '../../template/_header.php';
require '../_header.php';

$ord = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl);

$order = [
    'id' => $ord['id'],
];

/*
// faster params...
$order = [
    'id'      => '201810295863',
    'host_ref_num'  => '018711539490000181',
    'auth_code'     => '115394',
];
*/

$pos->prepare($order, \Mews\Pos\Gateways\AbstractGateway::TX_CANCEL);

// Cancel Order
$pos->cancel();

$response = $pos->getResponse();

?>
    <h4 class="text-center">NOT: Iptal islemi 12 saat (bankaya gore degisir) gecMEmis odeme icin yapilabilir</h4>
    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?= $pos->isSuccess() ? 'Cancel Order is successful!' : 'Cancel Order is not successful!'; ?>
        </h3>
        <dl class="row">
            <dt class="col-sm-12">All Data Dump:</dt>
            <dd class="col-sm-12">
                <pre><?php dump($response); ?></pre>
            </dd>
        </dl>
        <hr>
        <div class="text-right">
            <a href="index.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
        </div>
    </div>

<?php require '../../template/_footer.php';

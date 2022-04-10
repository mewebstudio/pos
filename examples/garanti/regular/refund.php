<?php

require '_config.php';
$templateTitle = 'Refund Order';
require '../../template/_header.php';
require '../_header.php';

use Mews\Pos\Gateways\AbstractGateway;

$ord = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl);

$order = [
    'id'          => $ord['id'],
    'ip'          => $ord['ip'],
    'email'       => $ord['email'],
    'amount'      => $ord['amount'],
    'currency'    => $ord['currency'],
    'ref_ret_num' => '831803586333',
];

$pos->prepare($order, AbstractGateway::TX_REFUND);

$pos->refund();

$response = $pos->getResponse();
?>
    <h4 class="text-center">NOT: Iade islemi 12 saati (bankaya gore degisir) gecmis odeme icin yapilabilir</h4>
    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?= $pos->isSuccess() ? 'Refund Order is successful!' : 'Refund Order is not successful!'; ?>
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

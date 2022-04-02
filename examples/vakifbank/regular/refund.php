<?php

use Mews\Pos\Gateways\AbstractGateway;

$templateTitle = 'Refund Order';
require '_config.php';
require '../../template/_header.php';
require '../_header.php';

$order = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip);
// Refund Order
$order = [
    'id'     => $order['id'], //ReferenceTransactionId
    'amount' => $order['amount'],
    'ip'     => $order['ip'],
];

$pos->prepare($order, AbstractGateway::TX_REFUND);

$pos->refund();

$response = $pos->getResponse();
?>

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


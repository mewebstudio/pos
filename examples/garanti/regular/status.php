<?php

$templateTitle = 'Order Status';
require '_config.php';
require '../../template/_header.php';
require '../_header.php';

use Mews\Pos\Gateways\AbstractGateway;

$ord = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip);

$order = [
    'id'       => $ord['id'],
    'currency' => $ord['currency'],
    'ip'       => $ord['ip'],
];

$pos->prepare($order, AbstractGateway::TX_STATUS);

$pos->status();

$response = $pos->getResponse();

?>

    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?= $pos->isSuccess() ? 'Query Order is successful!' : 'Query Order is not successful!'; ?>
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

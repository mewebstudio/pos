<?php

use Mews\Pos\Gateways\AbstractGateway;

$templateTitle = 'Order Status';
require '_config.php';
require '../../template/_header.php';
require '../_header.php';

$ord = $session->get('order');

$order = [
    'id' => $ord ? $ord['id'] : '973009309',
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
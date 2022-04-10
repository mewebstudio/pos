<?php

$templateTitle = 'Post Auth Order';
require '_config.php';
require '../../template/_header.php';
require '../_header.php';

use Mews\Pos\Gateways\AbstractGateway;

$baseUrl = $bankTestsUrl.'/regular/';

$ord = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip);

$order = [
    'id' => $ord['id'],
];

try {
    $pos->prepare($order, AbstractGateway::TX_POST_PAY);
} catch (\Mews\Pos\Exceptions\UnsupportedTransactionTypeException $e) {
    dump($e->getCode(), $e->getMessage());
}

$payment = $pos->payment();

$response = $payment->getResponse();
?>

    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?= $pos->isSuccess() ? 'Post Auth Order is successful!' : 'Post Auth Order is not successful!'; ?>
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

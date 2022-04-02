<?php

use Mews\Pos\Gateways\AbstractGateway;

$templateTitle = 'Post Auth Order (Ön Provizyonu, preAuth, tamamlama)';
require '_config.php';
require '../../template/_header.php';
require '../_header.php';

$order = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip);

$order = [
    'id'       => $order['id'],
    'amount'   => $order['amount'],
    'currency' => $order['currency'],
    'ip'       => $order['ip'],
];
try {
    $pos->prepare($order, AbstractGateway::TX_POST_PAY);
} catch (\Mews\Pos\Exceptions\UnsupportedTransactionTypeException $e) {
    dd($e);
}

$pos->payment();

$response = $pos->getResponse();
?>

    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?= $pos->isSuccess() ? 'Provisioning is successfully done!' : 'Provisioning is failed!'; ?>
        </h3>
        <dl class="row">
            <dt class="col-sm-12">All Data Dump:</dt>
            <dd class="col-sm-12">
                <pre><?php dump($response); ?></pre>
            </dd>
        </dl>
        <hr>
        <div class="text-right">
            <?php if ($pos->isSuccess()) : ?>
                <a href="cancel.php" class="btn btn-lg btn-info">&lt; Cancel payment</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
        </div>
    </div>

<?php require '../../template/_footer.php';

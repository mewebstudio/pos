<?php

use Mews\Pos\Gateways\AbstractGateway;

require '_config.php';

$templateTitle = 'Post Auth Order (Ön Provizyonu, preAuth, tamamlama)';

require '../../template/_header.php';

$order = (array) json_decode($redis->lPop('order'));
/*$order = [
    'id'       => '332323',
    'amount'   => 100,
    'currency' => 'TRY',
    'ip'       => $ip,
];*/
try {
    $pos->prepare($order, AbstractGateway::TX_POST_PAY);
} catch (\Mews\Pos\Exceptions\UnsupportedTransactionTypeException $e) {
    dump($e->getCode(), $e->getMessage());
}

$pos->payment();

$response = $pos->getResponse();

if ($pos->isSuccess()) {
    $redis->lPush('order', json_encode($order));
}
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
            <a href="credit-card-form.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
        </div>
    </div>

<?php require '../../template/_footer.php';

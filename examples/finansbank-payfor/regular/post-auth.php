<?php

require '_config.php';

$templateTitle = 'Post Auth Order (Ön Provizyonu, preAuth, iptal etme';

require '../../template/_header.php';

try {
    $pos = new \Mews\Pos\Pos($account);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$order = (array) json_decode($redis->lPop('order'));
$order['transaction'] = 'post';

try {
    $pos->prepare($order);
} catch (\Mews\Pos\Exceptions\UnsupportedTransactionTypeException $e) {
    dump($e->getCode(), $e->getMessage());
}
$card = new \Mews\Pos\Entity\Card\CreditCardPayFor('4155650100416111', '25', '01', '123', 'John Doe');

$payment = $pos->payment($card);

$response = $payment->getResponse();

if ($pos->isSuccess()) {
    $redis->lPush('order', json_encode($order));
}
?>

<div class="result">
    <h3 class="text-center text-<?php echo $pos->isSuccess() ? 'success' : 'danger'; ?>">
        <?php echo $pos->isSuccess() ? 'Provisioning is successfully done!' : 'Provisioning is failed!'; ?>
    </h3>
    <dl class="row">
        <dt class="col-sm-12">All Data Dump:</dt>
        <dd class="col-sm-12">
            <pre><?php dump($response); ?></pre>
        </dd>
    </dl>
    <hr>
    <?php if ($pos->isSuccess()) : ?>
        <div class="text-right">
            <a href="cancel.php" class="btn btn-lg btn-info">&lt; Cancel payment</a>
        </div>
    <?php endif;?>
    <hr>
    <div class="text-right">
        <a href="credit-card-form.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
    </div>
</div>

<?php require '../../template/_footer.php'; ?>

<?php

require '_config.php';

$templateTitle = 'Refund Order';

require '../../template/_header.php';

// Refund Order
$order = [
    'id'  => '20201101C02D',
    'amount'    => 100.01,
    'currency'      => 'TRY',
];

$pos->prepare($order, \Mews\Pos\Gateways\AbstractGateway::TX_REFUND);

$pos->refund();

$response = $pos->getResponse();
?>

<div class="result">
    <h3 class="text-center text-<?php echo $pos->isSuccess() ? 'success' : 'danger'; ?>">
        <?php echo $pos->isSuccess() ? 'Refund Order is successful!' : 'Refund Order is not successful!'; ?>
    </h3>
    <dl class="row">
        <dt class="col-sm-12">All Data Dump:</dt>
        <dd class="col-sm-12">
            <pre><?php dump($response); ?></pre>
        </dd>
    </dl>
    <hr>
    <div class="text-right">
        <a href="credit-card-form.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
    </div>
</div>

<?php require '../../template/_footer.php'; ?>

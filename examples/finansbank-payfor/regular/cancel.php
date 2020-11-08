<?php

require '_config.php';

$templateTitle = 'Cancel Order';

require '../../template/_header.php';

$ord = (array) json_decode($redis->lPop('order'));

$order = [
    'id'  => $ord['id'],
    'currency'  => $ord['currency'],
];

/*$order = [
    'id'  => '202011012784',
    'currency'  => 'TRY',
];*/

$pos->prepare($order);

// Cancel Order
$cancel = $pos->bank->cancel([]);

$response = $cancel->getResponse();

?>

<div class="result">
    <h3 class="text-center text-<?php echo $pos->isSuccess() ? 'success' : 'danger'; ?>">
        <?php echo $pos->isSuccess() ? 'Cancel Order is successful!' : 'Cancel Order is not successful!'; ?>
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

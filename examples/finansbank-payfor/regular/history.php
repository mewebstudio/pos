<?php

require '_config.php';

$templateTitle = 'Order History';

require '../../template/_header.php';

// History Order
$query= $pos->bank->history([
    //siparis tarihi
    'reqDate'  => '20201031',
    //veya siparis ID
    'orderId' => '20201031C06E',
]);

$response = $query->getResponse();
?>

<div class="result">
<!--    <h3 class="text-center text-<?php /*echo $pos->isSuccess() ? 'success' : 'danger'; */?>">
        <?php /*echo $pos->isSuccess() ? 'History Order is successful!' : 'History Order is not successful!'; */?>
    </h3>-->
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

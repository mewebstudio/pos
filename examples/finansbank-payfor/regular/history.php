<?php

$templateTitle = 'History Order';

require '_config.php';
require '../../template/_header.php';
require '../_header.php';

$ord = $session->get('order');

$order = [
    //siparis tarihi
    //'reqDate'  => '20201031',
    //veya siparis ID
    'order_id' => $ord ? $ord['id'] : '20201031C06E',
];

// History Order
$query = $pos->history($order);

$response = $query->getResponse();
?>

    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?= $pos->isSuccess() ? 'History Order is successful!' : 'History Order is not successful!'; ?>
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


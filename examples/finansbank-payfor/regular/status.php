<?php

require '_config.php';

$templateTitle = 'Order Status';

require '../../template/_header.php';

$ord = (array) json_decode($redis->lPop('order'));

$order = [
    'id'  => $ord['id'],
];

$pos->prepare($order, \Mews\Pos\Gateways\AbstractGateway::TX_STATUS);

$pos->status();

$response = $pos->getResponse();

?>

<div class="result">
    <h3 class="text-center text-<?php echo $response->proc_return_code === '00' ? 'success' : 'danger'; ?>">
        <?php echo $response->proc_return_code === '00' ? 'Query Order is successful!' : 'Query Order is not successful!'; ?>
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

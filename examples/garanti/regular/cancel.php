<?php

require '_config.php';

$templateTitle = 'Cancel Order';

require '../../template/_header.php';

$order = [
    'id'            => '20181114DF2C',
    'ip'            => $ip,
    'email'         => 'mail@customer.com',
    'ref_ret_num'   => '831803579226',
    'amount'        => 1,
    'currency'      => 'TRY',
];

$pos->prepare($order, \Mews\Pos\Gateways\AbstractGateway::TX_CANCEL);
// Cancel Order
$pos->cancel();

$response = $pos->getResponse();
?>

<div class="result">
    <h3 class="text-center text-<?php echo $response->proc_return_code === '00' ? 'success' : 'danger'; ?>">
        <?php echo $response->proc_return_code === '00' ? 'Cancel Order is successful!' : 'Cancel Order is not successful!'; ?>
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

<?php require '../../template/_footer.php'; ?>

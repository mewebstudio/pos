<?php

use Mews\Pos\Gateways\AbstractGateway;

require '_config.php';

$templateTitle = 'Refund Order';

require '../../template/_header.php';

$order = [
    'id'          => '201811142A0A',
    'ip'          => $ip,
    'email'       => 'mail@customer.com',
    'ref_ret_num' => '831803586333',
    'amount'      => 1,
    'currency'    => 'TRY',
];
$pos->prepare($order, AbstractGateway::TX_REFUND);
// Refund Order
$pos->refund();

$response = $pos->getResponse();
?>

    <div class="result">
        <h3 class="text-center text-<?= $response->proc_return_code === '00' ? 'success' : 'danger'; ?>">
            <?= $response->proc_return_code === '00' ? 'Refund Order is successful!' : 'Refund Order is not successful!'; ?>
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

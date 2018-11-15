<?php

require '_config.php';

$template_title = 'Refund Order';

require '../../template/_header.php';

// Refund Order
$refund = $pos->bank->refund([
    'order_id'      => '201811142A0A',
    'ip'            => $ip,
    'email'         => 'mail@customer.com',
    'ref_ret_num'   => '831803586333',
    'amount'        => 1,
    'currency'      => 'TRY',
]);

$response = $refund->response;
$dump = get_object_vars($response);
?>

<div class="result">
    <h3 class="text-center text-<?php echo $response->proc_return_code == '00' ? 'success' : 'danger'; ?>">
        <?php echo $response->proc_return_code == '00' ? 'Refund Order is successful!' : 'Refund Order is not successful!'; ?>
    </h3>
    <dl class="row">
        <dt class="col-sm-12">All Data Dump:</dt>
        <dd class="col-sm-12">
            <pre><?php print_r($dump); ?></pre>
        </dd>
    </dl>
    <hr>
    <div class="text-right">
        <a href="index.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
    </div>
</div>

<?php require '../../template/_footer.php'; ?>

<?php

require '_config.php';

$template_title = 'Order Status';

require '../../template/_header.php';

// Query Order
$query= $pos->bank->status([
    'order_id'  => '201811133F3F',
]);

$response = $query->response;
$dump = get_object_vars($response);
?>

<div class="result">
    <h3 class="text-center text-<?php echo $pos->isSuccess() ? 'success' : 'danger'; ?>">
        <?php echo $pos->isSuccess() ? 'Query Order is successful!' : 'Query Order is not successful!'; ?>
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

<?php

require '_config.php';

require '../../template/_header.php';

$order_id = date('Ymd') . strtoupper(substr(uniqid(sha1(time())),0,4));
$amount = (double) 1;
$instalment = '0';

$order = [
    'id'                => $order_id,
    'amount'            => $amount,
    'installment'       => $instalment,
    'currency'          => 'TRY',
    'success_url'       => $success_url,
    'fail_url'          => $fail_url,
    'transaction'       => 'pay', // pay => Sale, pre Auth
    'lang'              => 'tr',
];
?>

<form method="post" action="./form.php" role="form">
    <div class="row">
        <div class="form-group col-sm-12">
            <label for="name">Card Holder Name</label>
            <input type="text" name="name" id="name" class="form-control input-lg" placeholder="Card Holder Name">
        </div>
        <div class="form-group col-sm-3">
            <label for="type">Card Type</label>
            <select name="type" id="type" class="form-control input-lg">
                <option value="">Type</option>
                <option value="1">Visa</option>
                <option value="2">MasterCard</option>
            </select>
        </div>
        <div class="form-group col-sm-9">
            <label for="number">Card Number</label>
            <input type="text" name="number" id="number" class="form-control input-lg" placeholder="Credit card number">
        </div>
        <div class="form-group col-sm-4">
            <label for="month">Expire Month</label>
            <select name="month" id="month" class="form-control input-lg">
                <option value="">Month</option>
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo str_pad($i, 2, 0, STR_PAD_LEFT); ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group col-sm-4">
            <label for="year">Expire Year</label>
            <select name="year" id="year" class="form-control input-lg">
                <option value="">Year</option>
                <?php for ($i = date('y'); $i <= date('y') + 20; $i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo 2000 + $i; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group col-sm-4">
            <label for="cvv">Cvv</label>
            <input type="text" name="cvv" id="cvv" class="form-control input-lg" placeholder="Cvv">
        </div>
    </div>
    <input type="hidden" name="amount" value="<?php echo $order['amount']; ?>" />
    <input type="hidden" name="currency" value="<?php echo $order['currency']; ?>" />
    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>" />
    <input type="hidden" name="transaction" value="<?php echo $order['transaction']; ?>" />
    <input type="hidden" name="success_url" value="<?php echo $order['success_url']; ?>" />
    <input type="hidden" name="fail_url" value="<?php echo $order['fail_url']; ?>" />
    <input name="lang" type="hidden" id="lang" value="<?php echo $order['lang'] ?>">
    <hr>
    <div class="form-group text-center">
        <button type="submit" class="btn btn-lg btn-block btn-success">Payment</button>
    </div>
</form>

<?php require '../../template/_footer.php'; ?>

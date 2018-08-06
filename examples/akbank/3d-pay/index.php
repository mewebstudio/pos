<?php

require '_config.php';

require '../../template/_header.php';

?>

<form method="post" action="<?php echo $gateway; ?>" role="form">
    <div class="row">
        <div class="form-group col-sm-3">
            <label for="cardType">Card Type</label>
            <select name="cardType" id="cardType" class="form-control input-lg">
                <option value="">Type</option>
                <option value="1">Visa</option>
                <option value="2">MasterCard</option>
            </select>
        </div>
        <div class="form-group col-sm-9">
            <label for="pan">Card Number</label>
            <input type="text" name="pan" id="pan" class="form-control input-lg" placeholder="Credit card number">
        </div>
        <div class="form-group col-sm-4">
            <label for="Ecom_Payment_Card_ExpDate_Month">Expire Month</label>
            <select name="Ecom_Payment_Card_ExpDate_Month" id="Ecom_Payment_Card_ExpDate_Month" class="form-control input-lg">
                <option value="">Month</option>
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo str_pad($i, 2, 0, STR_PAD_LEFT); ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group col-sm-4">
            <label for="Ecom_Payment_Card_ExpDate_Year">Expire Year</label>
            <select name="Ecom_Payment_Card_ExpDate_Year" id="Ecom_Payment_Card_ExpDate_Year" class="form-control input-lg">
                <option value="">Year</option>
                <?php for ($i = date('y'); $i <= date('y') + 20; $i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo 2000 + $i; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group col-sm-4">
            <label for="cv2">Cvv</label>
            <input type="text" name="cv2" id="cv2" class="form-control input-lg" placeholder="Cvv">
        </div>
    </div>
    <?php if (isset($order['name'])): ?>
        <input type="hidden" name="firmaadi" value="<?php echo $order['name']; ?>">
    <?php endif; ?>
    <?php if (isset($order['email'])): ?>
        <input type="hidden" name="Email" value="<?php echo $order['email']; ?>">
    <?php endif; ?>
    <input type="hidden" name="clientid" value="<?php echo $account['client_id']; ?>" />
    <input type="hidden" name="amount" value="<?php echo $order['amount']; ?>" />
    <input type="hidden" name="islemtipi" value="<?php echo $order['transaction_type']; ?>" />
    <input type="hidden" name="taksit" value="<?php echo $order['installment']; ?>" />
    <input type="hidden" name="oid" value="<?php echo $order['id']; ?>" />
    <input type="hidden" name="okUrl" value="<?php echo $order['ok_url']; ?>" />
    <input type="hidden" name="failUrl" value="<?php echo $order['fail_url']; ?>" />
    <input type="hidden" name="rnd" value="<?php echo $rand; ?>" />
    <input type="hidden" name="hash" value="<?php echo $hash; ?>" />
    <input type="hidden" name="storetype" value="<?php echo $account['model']; ?>" />
    <input type="hidden" name="lang" value="<?php echo $order['lang']; ?>" />
    <input type="hidden" name="currency" value="<?php echo $currency; ?>" />
    <hr>
    <div class="form-group text-center">
        <button type="submit" class="btn btn-lg btn-block btn-success">Payment</button>
    </div>
</form>

<?php require '../../template/_footer.php'; ?>

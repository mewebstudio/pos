<?php

require '_config.php';

require '../../template/_header.php';

?>

<form method="post" action="<?php echo $gateway; ?>" role="form">
    <div class="row">
        <div class="form-group col-sm-12">
            <label for="name">Card Holder Number</label>
            <input type="text" name="name" id="name" class="form-control input-lg" placeholder="Card Holder Number">
        </div>
        <div class="form-group col-sm-12">
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
    <hr>
    <div class="form-group text-center">
        <button type="submit" class="btn btn-lg btn-block btn-success">Payment</button>
    </div>
</form>

<?php require '../../template/_footer.php'; ?>

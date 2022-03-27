<form method="post" action="<?= $url; ?>" role="form">
    <div class="row">
        <div class="row">
            <div class="form-group col-sm-12">
                <label for="name">Card holder name</label>
                <input type="text" name="name" id="name" class="form-control input-lg" placeholder="Card holder name"
                       value="<?= $card->getHolderName(); ?>">
            </div>
            <?php if (method_exists($card,'getCardTypeToCodeMapping')): ?>
            <div class="form-group col-sm-3">
                <label for="type">Card Type</label>
                <select name="type" id="type" class="form-control input-lg">
                    <option value="">Type</option>
                    <?php foreach ($card->getCardTypeToCodeMapping() as $key => $value): ?>
                    <option value="<?= $key ?>" <?= $key === $card->getType() ? 'selected' : '' ?>><?= $key ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group col-sm-9">
                <label for="number">Card Number</label>
                <input type="text" name="number" id="number" class="form-control input-lg"
                       placeholder="Credit card number" value="4090700101174272">
            </div>
            <div class="form-group col-sm-4">
                <label for="month">Expire Month</label>
                <select name="month" id="month" class="form-control input-lg">
                    <option value="">Month</option>
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i; ?>" <?= $i === 12 ? 'selected': null ?>><?= str_pad($i, 2, 0, STR_PAD_LEFT); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group col-sm-4">
                <label for="year">Expire Year</label>
                <select name="year" id="year" class="form-control input-lg">
                    <option value="">Year</option>
                    <?php for ($i = date('y'); $i <= date('y') + 20; $i++) : ?>
                        <option value="<?= $i; ?>" <?= $i === '22' ? 'selected': null ?>><?= 2000 + $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group col-sm-4">
                <label for="cvv">Cvv</label>
                <input type="text" name="cvv" id="cvv" class="form-control input-lg" placeholder="Cvv" value="104">
            </div>

            <div class="form-group col-xs-12">
                <select name="installment" id="installment" class="form-control input-lg">
                <?php foreach ($installments as $installment => $label): ?>
                    <option value="<?= $installment; ?>"><?= $label; ?></option>
                <?php endforeach; ?>
                </select>
            </div>
        </div>
        <hr>
        <div class="form-group text-center">
            <button type="submit" class="btn btn-lg btn-block btn-success">Payment</button>
        </div>
</form>

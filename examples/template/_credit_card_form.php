<form method="post" action="<?= $url; ?>" role="form">
    <div class="row">
        <div class="row">
            <div class="form-group col-sm-12">
                <label for="name">Card holder name</label>
                <input type="text" name="name" id="name" class="form-control input-lg" placeholder="Card holder name"
                       value="<?= $card->getHolderName(); ?>">
            </div>
            <?php if ($pos->getCardTypeMapping()): ?>
            <div class="form-group col-sm-3">
                <label for="type">Card Type</label>
                <select name="type" id="type" class="form-control input-lg">
                    <option value="">Type</option>
                    <?php foreach ($pos->getCardTypeMapping() as $key => $value): ?>
                        <option value="<?= $key ?>" <?= $key === $card->getType() ? 'selected' : '' ?>><?= $key ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group col-sm-9">
                <label for="number">Card Number</label>
                <input type="text" name="number" id="number" class="form-control input-lg"
                       placeholder="Credit card number" value="<?= $card->getNumber()?>">
            </div>
            <div class="form-group col-sm-4">
                <label for="month">Expire Month</label>
                <select name="month" id="month" class="form-control input-lg">
                    <option value="">Month</option>
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i; ?>" <?= $i == $card->getExpireMonth()  ? 'selected': null ?>><?= str_pad($i, 2, 0, STR_PAD_LEFT); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group col-sm-4">
                <label for="year">Expire Year</label>
                <select name="year" id="year" class="form-control input-lg">
                    <option value="">Year</option>
                    <?php for ($i = date('Y'); $i <= date('Y') + 20; $i++) : ?>
                        <option value="<?= $i; ?>" <?= $i == $card->getExpireYear('Y') ? 'selected': null ?>><?= $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group col-sm-4">
                <label for="cvv">Cvv</label>
                <input type="text" name="cvv" id="cvv" class="form-control input-lg" placeholder="Cvv" value="<?= $card->getCvv() ?>">
            </div>

            <div class="form-group col-md-4">
                <select name="installment" id="installment" class="form-control input-lg">
                <?php foreach ($installments as $installment => $label) : ?>
                    <option value="<?= $installment; ?>"><?= $label; ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <?php if ($pos->getCurrencies()): ?>
                <div class="form-group col-md-4">
                    <select name="currency" id="currency" class="form-control input-lg">
                        <?php foreach ($pos->getCurrencies() as $currency => $code) : ?>
                            <option value="<?= $currency; ?>" <?= $currency === 'TRY' ? 'selected': null ?>><?= $currency; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-group col-md-4">
                <select name="tx" id="currency" class="form-control input-lg">
                    <option value="<?= \Mews\Pos\Gateways\AbstractGateway::TX_PAY; ?>" selected>Ödeme</option>
                    <option value="<?= \Mews\Pos\Gateways\AbstractGateway::TX_PRE_PAY; ?>">Ön Provizyon</option>
                </select>
            </div>
            <div class="form-group col-md-4">
                <select name="lang" id="lang" class="form-control input-lg">
                    <?php foreach ($pos->getLanguages() as $lang) : ?>
                        <option value="<?= $lang; ?>" <?= $lang === \Mews\Pos\Gateways\AbstractGateway::LANG_TR ? 'selected': null ?>><?= strtoupper($lang); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-4">
                <div class="form-group">
                    <label class="form-check-label" for="isRecurringPayment">
                    <input type="checkbox" class="form-check-input" id="isRecurringPayment" name="is_recurring" value="1">
                        Tekrarlanan Ödeme
                    <small class="form-text text-muted">henuz butun gatewayler'e bu ozellik destegi eklenmedi.</small>
                    </label>
                </div>
            </div>
        </div>
        <hr>
        <div class="form-group text-center">
            <button type="submit" class="btn btn-lg btn-block btn-success">Payment</button>
        </div>
</form>

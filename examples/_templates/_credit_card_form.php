<form method="post" action="<?= $url; ?>" role="form">
    <div class="row">
        <div class="row">
            <div class="mb-3 col-sm-12">
                <label for="name">Card holder name</label>
                <input type="text" name="name" id="name" class="form-control input-lg" placeholder="Card holder name"
                       value="<?= $card->getHolderName(); ?>">
            </div>
            <?php if ($pos->getCardTypeMapping()): ?>
            <div class="mb-3 col-sm-3">
                <label for="type">Card Type</label>
                <select name="type" id="type" class="form-select input-lg">
                    <option value="">Type</option>
                    <?php foreach ($pos->getCardTypeMapping() as $key => $value): ?>
                        <option value="<?= $key ?>" <?= $key === $card->getType() ? 'selected' : '' ?>><?= $key ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="mb-3 col-sm-9">
                <label for="number">Card Number</label>
                <input type="text" name="number" id="number" class="form-control input-lg"
                       placeholder="Credit card number" value="<?= $card->getNumber()?>">
            </div>
            <div class="mb-3 col-sm-4">
                <label for="month">Expire Month</label>
                <select name="month" id="month" class="form-select input-lg">
                    <option value="">Month</option>
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i; ?>" <?= $i == $card->getExpireMonth()  ? 'selected': null ?>><?= str_pad($i, 2, 0, STR_PAD_LEFT); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="mb-3 col-sm-4">
                <label for="year">Expire Year</label>
                <select name="year" id="year" class="form-select input-lg">
                    <option value="">Year</option>
                    <?php for ($i = date('Y'); $i <= date('Y') + 30; $i++) : ?>
                        <option value="<?= $i; ?>" <?= $i == $card->getExpireYear('Y') ? 'selected': null ?>><?= $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="mb-3 col-sm-4">
                <label for="cvv">Cvv</label>
                <input type="text" name="cvv" id="cvv" class="form-control input-lg" placeholder="Cvv" value="<?= $card->getCvv() ?>">
            </div>

            <div class="mb-3 col-md-4">
                <select name="installment" id="installment" class="form-select input-lg">
                <?php foreach ($installments as $installment => $label) : ?>
                    <option value="<?= $installment; ?>"><?= $label; ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <?php if ([] !== $pos->getCurrencies()): ?>
                <div class="mb-3 col-md-4">
                    <select name="currency" id="currency" class="form-select input-lg">
                        <?php foreach ($pos->getCurrencies() as $currency) : ?>
                            <option value="<?= $currency; ?>" <?= $currency === \Mews\Pos\PosInterface::CURRENCY_TRY ? 'selected': null ?>><?= $currency; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="mb-3 col-md-4">
                <select name="tx" id="currency" class="form-select input-lg">
                    <option value="<?= \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH; ?>" selected>Ödeme</option>
                    <?php if ($pos::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_PRE_AUTH, $paymentModel)): ?>
                        <option value="<?= \Mews\Pos\PosInterface::TX_TYPE_PAY_PRE_AUTH; ?>">Ön Provizyon</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="mb-3 col-md-4">
                <select name="lang" id="lang" class="form-select input-lg">
                    <?php foreach ($pos->getLanguages() as $lang) : ?>
                        <option value="<?= $lang; ?>" <?= $lang === \Mews\Pos\PosInterface::LANG_TR ? 'selected': null ?>><?= strtoupper($lang); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3 col-md-4">
                <div class="form-group">
                    <label class="form-check-label" for="isRecurringPayment">
                    <input type="checkbox" class="form-check-input" id="isRecurringPayment" name="is_recurring" value="1">
                        Tekrarlanan Ödeme
                    <small class="form-text text-muted">henuz butun gatewayler'e bu ozellik destegi eklenmedi.</small>
                    </label>
                </div>
            </div>
            <div class="mb-3 col-xs-12">
                <?php if ($paymentModel !== \Mews\Pos\PosInterface::MODEL_NON_SECURE): ?>
                    <div class="form-check form-check-inline">
                        <input type="radio" class="form-check-input" name="payment_flow_type" value="by_redirection" checked>
                        <label class="form-check-label">Redirektli ödeme</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" class="form-check-input" name="payment_flow_type" value="by_iframe">
                        <label class="form-check-label">Modal box'da ödeme</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" class="form-check-input" name="payment_flow_type" value="by_popup_window">
                        <label class="form-check-label">Popup Windowda ödeme</label>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <hr>
        <div class="mb-3 text-center">
            <button type="submit" class="btn btn-lg btn-block btn-success">Payment</button>
        </div>
</form>

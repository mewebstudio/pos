<!DOCTYPE HTML>
<html lang="tr">
<head>
    <title><?= $templateTitle; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"
            integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13"
            crossorigin="anonymous"></script>
</head>
<body>
<header class="bs-docs-nav navbar navbar-static-top" id="top">
        <nav class="navbar navbar-expand-lg navbar-light bg-light" id="bs-navbar">
            <div class="container-fluid">
                <div class="collapse navbar-collapse"  id="navbarContent">
                    <div class="w-100 d-lg-flex justify-content-lg-between">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $hostUrl ?>">Main Page</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\ToslaPos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/tosla/index.php">Tosla (Ak Öde)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\AkbankPos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/akbankpos/index.php">Akbank POS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\ParamPos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/parampos/index.php">Param POS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\EstV3Pos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/payten/index.php">Payten V3</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\PayForPos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/finansbank-payfor/index.php">PayFor (Finansbank)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\GarantiPos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/garanti/index.php">Garanti POS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\InterPos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/interpos/index.php">InterPos (Deniz bank)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\PayFlexV4Pos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/payflex-mpi-v4/index.php">PayFlex MPI V4 (VakifBank VPOS 7/24)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\PayFlexCPV4Pos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/payflex-cp-v4/index.php">PayFlex Common Payment V4 (VakifBank)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\PosNet::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/posnet-ykb/index.php">PosNet (YKB)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\PosNetV1Pos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/posnet-v1/index.php">PosNetV1 (Albaraka)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\KuveytPos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/kuveytpos/index.php">KuveytPOS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $posClass === \Mews\Pos\Gateways\VakifKatilimPos::class ? 'active' : ''; ?>" href="<?= $hostUrl ?>/vakif-katilim/index.php">VakifKatilimPos</a>
                        </li>
                    </ul>
                </div>
                </div>
            </div>
        </nav>
        <nav class="navbar navbar-expand-lg navbar-light bg-light" id="bs-navbar">
            <div class="container-fluid">
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                    </ul>
                </div>
            </div>
        </nav>
</header>
<div id="wrapper">
    <div class="container" style="max-width: 720px;">
        <h2 class="text-center"><?= $templateTitle; ?></h2>
        <hr>
        <?php if (isset($posClass)): ?>
            <nav class="navbar navbar-expand-lg navbar-light bg-light" id="bs-navbar">
                <div class="container-fluid">
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            <?php if ($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, \Mews\Pos\PosInterface::MODEL_3D_SECURE)): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $paymentModel === \Mews\Pos\PosInterface::MODEL_3D_SECURE ? 'active' : ''; ?>" href="<?= $bankTestsUrl ?>/3d/index.php">3D Ödeme</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, \Mews\Pos\PosInterface::MODEL_3D_PAY)): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $paymentModel === \Mews\Pos\PosInterface::MODEL_3D_PAY ? 'active' : ''; ?>" href="<?= $bankTestsUrl ?>/3d-pay/index.php">3D Pay Ödeme</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, \Mews\Pos\PosInterface::MODEL_3D_PAY_HOSTING)): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $paymentModel === \Mews\Pos\PosInterface::MODEL_3D_PAY_HOSTING ? 'active' : ''; ?>" href="<?= $bankTestsUrl ?>/3d-pay-hosting/index.php">3D Pay Hosting Ödeme</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, \Mews\Pos\PosInterface::MODEL_3D_HOST)): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $paymentModel === \Mews\Pos\PosInterface::MODEL_3D_HOST ? 'active' : ''; ?>" href="<?= $bankTestsUrl ?>/3d-host/index.php">3D Host Ödeme</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $paymentModel === \Mews\Pos\PosInterface::MODEL_NON_SECURE && ($transaction === \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH)? 'active' : ''; ?>"
                                       href="<?= $bankTestsUrl ?>/regular/index.php">Non Secure Ödeme</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_STATUS, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $transaction === \Mews\Pos\PosInterface::TX_TYPE_STATUS ? 'active' : ''; ?>" href="<?= $bankTestsUrl ?>/regular/status.php">Ödeme Durumu</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_CANCEL, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $transaction === \Mews\Pos\PosInterface::TX_TYPE_CANCEL ? 'active' : ''; ?>" href="<?= $bankTestsUrl ?>/regular/cancel.php">İptal</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_REFUND, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $transaction === \Mews\Pos\PosInterface::TX_TYPE_REFUND ? 'active' : ''; ?>" href="<?= $bankTestsUrl ?>/regular/refund.php">İade</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_ORDER_HISTORY, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $transaction === \Mews\Pos\PosInterface::TX_TYPE_ORDER_HISTORY ? 'active' : ''; ?>" href="<?= $bankTestsUrl ?>/regular/order_history.php">Order History</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_HISTORY, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $transaction === \Mews\Pos\PosInterface::TX_TYPE_HISTORY ? 'active' : ''; ?>" href="<?= $bankTestsUrl ?>/regular/history.php">History</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_CUSTOM_QUERY, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $transaction === \Mews\Pos\PosInterface::TX_TYPE_CUSTOM_QUERY ? 'active' : ''; ?>" href="<?= $bankTestsUrl ?>/regular/custom_query.php">Custom Query</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </nav>
        <?php endif; ?>
        <hr>


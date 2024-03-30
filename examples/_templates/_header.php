<!DOCTYPE HTML>
<html lang="tr">
<head>
    <title><?= $templateTitle; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"
          integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css"
          integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
</head>
<body>

<header class="bs-docs-nav navbar navbar-static-top" id="top">
    <div class="container">
        <div class="navbar-header">
            <button aria-controls="bs-navbar" aria-expanded="false" class="collapsed navbar-toggle" data-target="#bs-navbar" data-toggle="collapse" type="button">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a href="<?= $hostUrl ?>" class="navbar-brand">Main Page</a>
        </div>
        <nav class="collapse navbar-collapse" id="bs-navbar">
            <ul class="nav navbar-nav">
                <li> <a href="<?= $hostUrl ?>/tosla/index.php">Tosla (Ak Öde)</a></li>
                <li> <a href="<?= $hostUrl ?>/payten/index.php">Payten V3</a></li>
                <li> <a href="<?= $hostUrl ?>/finansbank-payfor/index.php">PayFor (Finansbank)</a></li>
                <li> <a href="<?= $hostUrl ?>/garanti/index.php">Garanti POS</a></li>
                <li> <a href="<?= $hostUrl ?>/interpos/index.php">InterPos (Deniz bank)</a></li>
                <li> <a href="<?= $hostUrl ?>/payflex-mpi-v4/index.php">PayFlex MPI V4 (VakifBank VPOS 7/24)</a></li>
                <li> <a href="<?= $hostUrl ?>/payflex-cp-v4/index.php">PayFlex Common Payment V4 (VakifBank)</a></li>
                <li> <a href="<?= $hostUrl ?>/posnet-ykb/index.php">PosNet (YKB)</a></li>
                <li> <a href="<?= $hostUrl ?>/posnet-v1/index.php">PosNetV1 (Albaraka)</a></li>
                <li> <a href="<?= $hostUrl ?>/kuveytpos/index.php">KuveytPOS</a></li>
            </ul>
        </nav>
    </div>
</header>
<div id="wrapper">
    <div class="container" style="max-width: 640px;">
        <h2 class="text-center"><?= $templateTitle; ?></h2>
        <hr>
        <?php if(isset($posClass)): ?>
        <nav class="collapse navbar-collapse" id="sub-navbar">
            <ul class="nav navbar-nav">
                <?php if($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, \Mews\Pos\PosInterface::MODEL_3D_SECURE)): ?>
                    <li> <a href="<?= $bankTestsUrl ?>/3d/index.php">3D Ödeme</a></li>
                <?php endif; ?>
                <?php if($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, \Mews\Pos\PosInterface::MODEL_3D_PAY)): ?>
                    <li> <a href="<?= $bankTestsUrl ?>/3d-pay/index.php">3D Pay Ödeme</a></li>
                <?php endif; ?>
                <?php if($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, \Mews\Pos\PosInterface::MODEL_3D_PAY_HOSTING)): ?>
                    <li> <a href="<?= $bankTestsUrl ?>/3d-pay-hosting/index.php">3D Pay Hosting Ödeme</a></li>
                <?php endif; ?>
                <?php if($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, \Mews\Pos\PosInterface::MODEL_3D_HOST)): ?>
                    <li> <a href="<?= $bankTestsUrl ?>/3d-host/index.php">3D Host Ödeme</a></li>
                <?php endif; ?>
                <?php if($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                    <li> <a href="<?= $bankTestsUrl ?>/regular/index.php">Non Secure Ödeme</a></li>
                <?php endif; ?>
                <?php if($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_STATUS, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                    <li> <a href="<?= $bankTestsUrl ?>/regular/status.php">Ödeme Durumu</a></li>
                <?php endif; ?>
                <?php if($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_CANCEL, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                    <li> <a href="<?= $bankTestsUrl ?>/regular/cancel.php">İptal</a></li>
                <?php endif; ?>
                <?php if($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_REFUND, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                    <li> <a href="<?= $bankTestsUrl ?>/regular/refund.php">İade</a></li>
                <?php endif; ?>
                <?php if($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_ORDER_HISTORY, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                    <li> <a href="<?= $bankTestsUrl ?>/regular/order_history.php">Order History</a></li>
                <?php endif; ?>
                <?php if($posClass::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_HISTORY, \Mews\Pos\PosInterface::MODEL_NON_SECURE)): ?>
                    <li> <a href="<?= $bankTestsUrl ?>/regular/history.php">History</a></li>
                <?php endif; ?>

            </ul>
        </nav>
        <?php endif; ?>
        <hr>


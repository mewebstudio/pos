<?php
/*$redis = new Redis();
$redis->connect('pos_redis', 6379);
$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler($redis);*/
$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
    'cookie_samesite' => 'None'
]);
$session = new \Symfony\Component\HttpFoundation\Session\Session($sessionHandler);
?>
<nav class="collapse navbar-collapse" id="sub-navbar">
    <ul class="nav navbar-nav">
        <li> <a href="<?= $hostUrl ?>/interpos/3d">3D Ödeme</a></li>
        <li> <a href="<?= $hostUrl ?>/interpos/3d-pay">3D Pay Ödeme</a></li>
        <li> <a href="<?= $hostUrl ?>/interpos/3d-host">3D Host Ödeme</a></li>
        <li> <a href="<?= $hostUrl ?>/interpos/regular">Non Secure Ödeme</a></li>
        <li> <a href="<?= $hostUrl ?>/interpos/regular/cancel.php">Iptal</a></li>
        <li> <a href="<?= $hostUrl ?>/interpos/regular/refund.php">Iade</a></li>
        <li> <a href="<?= $hostUrl ?>/interpos/regular/status.php">Durum Sorgulama</a></li>
    </ul>
</nav>
<hr>

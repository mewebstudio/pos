<?php

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/kuveytsoappos';
$posClass = \Mews\Pos\Gateways\KuveytSoapApiPos::class;

<?php

session_start();

$root = realpath($_SERVER["DOCUMENT_ROOT"]);
require_once "$root/../vendor/autoload.php";

$hostUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')."://$_SERVER[HTTP_HOST]";

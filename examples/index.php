<?php
/**
 * Docker Apache PHP container needs this file.
 * without it (with current configuration) Apache throws error
 */
$templateTitle = 'POS';
require './_main_config.php';
require './template/_header.php';
require './template/_footer.php';

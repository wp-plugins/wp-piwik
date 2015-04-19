<?php
	
require('../../../../wp-load.php');
require_once('../classes/WP_Piwik/Settings.php');
require_once('../classes/WP_Piwik/Logger.php');
require_once('../classes/WP_Piwik/Logger/Dummy.php');

$logger = new WP_Piwik\Logger\Dummy(__CLASS__);
$settings = new WP_Piwik\Settings(null, $logger);

$PIWIK_URL = $settings->getGlobalOption('piwik_url');
$TOKEN_AUTH = $settings->getGlobalOption('piwik_token');
$timeout = $settings->getGlobalOption('connection_timeout');
ini_set('display_errors',0);
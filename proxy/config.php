<?php
require('../../../../wp-load.php');

require_once('../classes/WP_Piwik/Settings.php');
require_once('../classes/WP_Piwik/Logger.php');
require_once('../classes/WP_Piwik/Logger/Dummy.php');

$logger = new WP_Piwik\Logger\Dummy(__CLASS__);
$settings = new WP_Piwik\Settings($logger);

$PIWIK_URL = $settings->getGlobalOption('piwik_mode') == 'php'?$settings->getGlobalOption('proxy_url'):$settings->getGlobalOption('piwik_url');

if (substr($PIWIK_URL, 0, 2) == '//')
	$PIWIK_URL = (isset($_SERVER['HTTPS'])?'https:':'http:').$PIWIK_URL;

$TOKEN_AUTH = $settings->getGlobalOption('piwik_token');
$timeout = $settings->getGlobalOption('connection_timeout');

ini_set('display_errors',1);
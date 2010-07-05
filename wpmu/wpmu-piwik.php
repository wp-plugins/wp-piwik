<?php
	/* WPMU WP-Piwik loader
	 * 
	 *  1. Copy the whole wp-piwik folder to /wp-content/mu-plugins/
	 *  2. Copy /wp-content/mu-plugins/wp-piwik/wpmu/wpmu-piwik.php to /wp-content/mu-plugins/wpmu-piwik.php
	 */ 

	$GLOBALS['wp-piwik_wpmu'] = true;
	require_once('wp-piwik/wp-piwik.php');

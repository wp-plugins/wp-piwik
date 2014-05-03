<?php
/*
Plugin Name: WP-Piwik

Plugin URI: http://wordpress.org/extend/plugins/wp-piwik/

Description: Adds Piwik stats to your dashboard menu and Piwik code to your wordpress header.

Version: 0.9.9.10
Author: Andr&eacute; Br&auml;kling
Author URI: http://www.braekling.de

****************************************************************************************** 
	Copyright (C) 2009-2014 Andre Braekling (email: webmaster@braekling.de)

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*******************************************************************************************/

if (!function_exists ('add_action')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

function wp_piwik_php_ver_error() {
	echo '<div class="error"><p>WP-Piwik requires at least PHP 5.3. You are using PHP '.PHP_VERSION.'. Please update to a recent version.</p></div>';
}

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
	add_action('admin_notices', wp_piwik_php_ver_error());
} else {
	require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'config.php');
	if (!class_exists('wp_piwik'))
		require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'WP_Piwik.php');
	if (class_exists('wp_piwik'))
		$GLOBALS['wp_piwik'] = new wp_piwik();
}
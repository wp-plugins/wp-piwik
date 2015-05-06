<?php
// Get & delete old version's options
if (self::$settings->checkNetworkActivation ()) {
	$oldGlobalOptions = get_site_option ( 'wp-piwik_global-settings', array () );
	delete_site_option ( 'wp-piwik_global-settings' );
} else {
	$oldGlobalOptions = get_option ( 'wp-piwik_global-settings', array () );
	delete_option ( 'wp-piwik_global-settings' );
}
$oldOptions = get_option ( 'wp-piwik_settings', array () );
delete_option ( 'wp-piwik_settings' );

// Store old values in new settings
foreach ( $oldGlobalOptions as $key => $value )
	self::$settings->setGlobalOption ( $key, $value );
foreach ( $oldOptions as $key => $value )
	self::$settings->setOption ( $key, $value );
self::$settings->save ();
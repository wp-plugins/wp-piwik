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

if (!$oldGlobalOptions['add_tracking_code']) $oldGlobalOptions['track_mode'] = 'disabled';
elseif ($oldGlobalOptions['track_mode'] == 0) $oldGlobalOptions['track_mode'] = 'default';
elseif ($oldGlobalOptions['track_mode'] == 1) $oldGlobalOptions['track_mode'] = 'js';
elseif ($oldGlobalOptions['track_mode'] == 2) $oldGlobalOptions['track_mode'] = 'proxy';

// Store old values in new settings
foreach ( $oldGlobalOptions as $key => $value )
	self::$settings->setGlobalOption ( $key, $value );
foreach ( $oldOptions as $key => $value )
	self::$settings->setOption ( $key, $value );
self::$settings->save ();
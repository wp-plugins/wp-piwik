<?php
$bolCURL = function_exists('curl_init');
$bolFOpen = ini_get('allow_url_fopen');
if (!$bolFOpen && !$bolCURL) {
?><tr>	
	<td colspan="2">
		<strong><?php _e('Error: cURL is not enabled and fopen is not allowed to open URLs. WP-Piwik won\'t be able to connect to Piwik.'); ?></strong>
	</td>
</tr><?php } else { ?><tr>
	<th colspan="2">
		<?php _e('To enable Piwik statistics, please enter your Piwik base URL (like http://mydomain.com/piwik) and your personal authentification token. You can get the token on the API page inside your Piwik interface. It looks like &quot;1234a5cd6789e0a12345b678cd9012ef&quot;.', 'wp-piwik'); ?>
	</th>
</tr><?php if (!is_plugin_active_for_network('wp-piwik/wp-piwik.php')) { ?><tr>
	<th colspan="2">
		<?php _e('<strong>Important note:</strong> If you do not host this blog on your own, your site admin is able to get your auth token from the database. So he is able to access your statistics. In this case you should never use an auth token with more than simple view access although &quot;Auto config&quot; won\'t work!', 'wp-piwik'); ?>
	</th>
</tr><?php } ?><tr>
	<th><?php _e('Piwik URL', 'wp-piwik'); ?>:</th>
	<td>
		<input id="wp-piwik_url" name="wp-piwik_url" type="text" value="<?php echo self::$aryGlobalSettings['piwik_url']; ?>" />
		<label for="wp-piwik_url"></label>
	</td>
</tr><tr>
	<th><?php _e('Auth token', 'wp-piwik'); ?>:</th>
	<td>
		<input name="wp-piwik_token" id="wp-piwik_token" type="text" value="<?php echo self::$aryGlobalSettings['piwik_token']; ?>" />
		<label for="wp-piwik_url"></label>
	</td>
</tr><?php if (!is_plugin_active_for_network('wp-piwik/wp-piwik.php')) { ?><tr>
	<th><?php _e('Auto config', 'wp-piwik'); ?>:</th>
	<td>
		<input name="wp-piwik_auto_site_config" id="wp-piwik_auto_site_config" value="1" type="checkbox"<?php echo (self::$aryGlobalSettings['auto_site_config']?' checked="checked"':'') ?>/>
		<label for="wp-piwik_auto_site_config"><?php _e('Check this to automatically choose your blog from your Piwik sites by URL. If your blog is not added to Piwik yet, WP-Piwik will add a new site.', 'wp-piwik') ?></label>
	</td>
</tr><?php }} ?>
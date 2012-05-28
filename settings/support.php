<tr>
	<td><a href="http://peepbo.de/board/viewforum.php?f=3"><?php _e('WP-Piwik support board','wp-piwik'); ?></a> (<?php _e('no registration required, English &amp; German','wp-piwik'); ?>)</td>	
</tr>
<tr>
	<td><a href="http://wordpress.org/tags/wp-piwik?forum_id=10"><?php _e('WordPress.org forum about WP-Piwik','wp-piwik'); ?></a> (<?php _e('WordPress.org registration required, English','wp-piwik'); ?>)</td>
</tr>
<tr>
	<td><?php _e('Please don\'t forget to vote the compatibility at the','wp-piwik'); ?> <a href="http://wordpress.org/extend/plugins/wp-piwik/">WordPress.org Plugin Directory</a>.</td>
</tr>
<tr>
	<td>
		<h3><?php _e('Debugging', 'wp-piwik'); ?></h3>
		<p><?php _e('Either allow_url_fopen has to be enabled <em>or</em> cURL has to be available:') ?></p>
		<ol>
			<li><?php 
				_e('cURL is','wp-piwik');
				echo ' <strong>'.(function_exists('curl_init')?'':__('not','wp-piwik')).' ';
				_e('available','wp-piwik');
			?></strong>.</li>
			<li><?php 
				_e('allow_url_fopen is','wp-piwik');
				echo ' <strong>'.(ini_get('allow_url_fopen')?'':__('not','wp-piwik')).' ';
				_e('enabled','wp-piwik');
			?></strong>.</li>
		</ol>
<?php if (!(empty(self::$aryGlobalSettings['piwik_token']) || empty(self::$aryGlobalSettings['piwik_url']))) { ?>
<?php 
	if (isset($_GET['mode']) && $_GET['mode'] == 'testscript') {
		echo '<p><strong>Test script result</strong></p>';
		self::loadTestscript();
	} 
?>
		<p><strong>Get more debug information:</strong></p>
		<ol>
			<li><a href="?page=wp-piwik/wp-piwik.php&tab=support&mode=testscript">Run test script</a></li>
			<li><a href="?page=wp-piwik/wp-piwik.php&tab=sitebrowser">Get site configuration details</a></li>
		</ol>
<?php } else echo '<p>'.__('You have to enter your auth token and the Piwik URL before you can access more debug functions.', 'wp-piwik').'</p>'; ?>
	</td>
</tr>
<tr><td><h3>Latest support threads on WordPress.org</h3>
<?php 
	$arySupportThreads = self::readRSSFeed('http://wordpress.org/support/rss/tags/wp-piwik');
	if (!empty($arySupportThreads)) {
		echo '<ol>';
		foreach ($arySupportThreads as $arySupportThread) echo '<li><a href="'.$arySupportThread['url'].'">'.$arySupportThread['title'].'</a></li>';
		echo '</ol>';
	}
?></td></tr>
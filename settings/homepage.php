<?php
$strVersion = $this->callPiwikAPI('ExampleAPI.getPiwikVersion');
// http://wordpress.org/support/rss/tags/wp-piwik
?><tr><td><strong><?php _e('Thanks for using WP-Piwik!', 'wp-piwik'); ?></strong></td></tr>
<tr><td><?php 
if (is_array($strVersion) && $strVersion['result'] == 'error') self::showErrorMessage($strVersion['message']);
elseif (empty($strVersion)) self::showErrorMessage('Piwik did not answer. Please check your entered Piwik URL.');
else echo __('You are using Piwik','wp-piwik').' '.$strVersion.' '.__('and', 'wp-piwik').' WP-Piwik '.self::$strVersion.'.';
?></td></tr>
<tr><td><h3>Latest support threads on WordPress.org</h3>
<?php 
	$arySupportThreads = self::readRSSFeed('http://wordpress.org/support/rss/tags/wp-piwik');
	if (!empty($arySupportThreads)) {
		echo '<ol>';
		foreach ($arySupportThreads as $arySupportThread) echo '<li><a href="'.$arySupportThread['url'].'">'.$arySupportThread['title'].'</a></li>';
		echo '</ol>';
	}
?></td></tr>
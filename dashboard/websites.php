<?php
/*********************************
	WP-Piwik::Stats:Websites
**********************************/

	$aryConf['data'] = $this->call_API(
		'Referers.getWebsites',
		$aryConf['params']['period'],
		$aryConf['params']['date'],
		$aryConf['params']['limit']
	);
	$aryConf['title'] = __('Websites', 'wp-piwik');
	include('header.php');
/***************************************************************************/ ?>
<table class="widefat">
	<thead>
		<tr>
			<th><?php _e('Website', 'wp-piwik'); ?></th>
			<th><?php _e('Unique', 'wp-piwik'); ?></th>
		</tr>
	</thead>
	<tbody>
<?php /************************************************************************/
	if (is_array($aryConf['data'])) foreach ($aryConf['data'] as $aryValues)
		echo '<tr><td>'.$aryValues['label'].'</td><td>'.$aryValues['nb_uniq_visitors'].'</td></tr>';
	else echo '<tr><td colspan="2">'.__('No data available.', 'wp-piwik').'</td></tr>';
/***************************************************************************/ ?>
	</tbody>
</table>
<?php /************************************************************************/
	include ('footer.php');

/* EOF */

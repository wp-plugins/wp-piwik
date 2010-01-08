<?php 
/*********************************
	WP-Piwik::Stats:Overview
**********************************/

	$aryConf['data'] = $this->call_API(
		'VisitsSummary.get',
		$aryConf['params']['period'],
		$aryConf['params']['date'],
		$aryConf['params']['limit']
	);
	$aryConf['title'] = __('Overview', 'wp-piwik');
	if (!isset($aryConf['inline']) || isset($aryConf['inline']) != true)
		include('header.php');
/***************************************************************************/ ?>
<div class="table">
	<table class="widefat">
		<tbody>
<?php /************************************************************************/
	$strTime = 
		floor($aryConf['data']['sum_visit_length']/3600).'h '.
		floor(($aryConf['data']['sum_visit_length'] % 3600)/60).'m '.
		floor(($aryConf['data']['sum_visit_length'] % 3600) % 60).'s';
	echo '<tr><td>'.__('Visitors', 'wp-piwik').':</td><td>'.$aryConf['data']['nb_visits'].'</td></tr>';
	echo '<tr><td>'.__('Unique visitors', 'wp-piwik').':</td><td>'.$aryConf['data']['nb_uniq_visitors'].'</td></tr>';
	echo '<tr><td>'.__('Page views', 'wp-piwik').':</td><td>'.$aryConf['data']['nb_actions'].'</td></tr>';
	echo '<tr><td>'.__('Max. page views in one visit', 'wp-piwik').':</td><td>'.$aryConf['data']['max_actions'].'</td></tr>';
	echo '<tr><td>'.__('Total time spent by visitors', 'wp-piwik').':</td><td>'.$strTime.'</td></tr>';
	echo '<tr><td>'.__('Bounce count', 'wp-piwik').':</td><td>'.$aryConf['data']['bounce_count'].'</td></tr>';
/***************************************************************************/ ?>
		</tbody>
	</table>
</div>
<?php /************************************************************************/
	if (!isset($aryConf['inline']) || isset($aryConf['inline']) != true)
		include ('footer.php');

/* EOF */

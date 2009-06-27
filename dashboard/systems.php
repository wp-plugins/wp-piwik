<?php
/*********************************
	WP-Piwik::Stats:Systems
**********************************/

	$aryConf['data'] = $this->call_API(
			'UserSettings.getOS', 
			$aryConf['params']['period'], 
			$aryConf['params']['date'],
			$aryConf['params']['limit']
	);
	$aryConf['title'] = __('Operating System', 'wp-piwik');
	include('header.php');
	$strValues = $strLabels = '';
	$intSum = 0;
	foreach ($aryConf['data'] as $aryValues)
		$intSum += $aryValues['nb_uniq_visitors'];
	foreach ($aryConf['data'] as $aryValues) {
		$strValues .= round(($aryValues['nb_uniq_visitors']/$intSum*100), 2).',';
		$strLabels .= '|'.urlencode($aryValues['label']);
	}
	$strValues = substr($strValues, 0, -1);
	$strLabels = substr($strLabels, 1);
	$strBase  = 'http://chart.apis.google.com/chart?'.
		'cht=p&amp;'.
		'chs=500x220&amp;'.
		'chd=t:'.$strValues.'&amp;'.
		'chl='.$strLabels.'&amp;'.
		'chco=90AAD9,A0BAE9&amp;';
/***************************************************************************/ ?>
<div class="wp-piwik-graph-wide">
	<img src="<?php echo $strBase.$strGraph; ?>" width="500" height="220" alt="Visits graph" />
</div>
<div class="table">
	<table class="widefat wp-piwik-table">
		<thead>
			<tr>
				<th><?php _e('Operating System', 'wp-piwik'); ?></th>
				<th class="n"><?php _e('Unique', 'wp-piwik'); ?></th>
				<th class="n"><?php _e('Percent', 'wp-piwik'); ?></th>
			</tr>
		</thead>
		<tbody>
<?php /************************************************************************/
	foreach ($aryConf['data'] as $aryValues)
		echo '<tr><td>'.
				$aryValues['label'].
			'</td><td class="n">'.
				$aryValues['nb_uniq_visitors'].
			'</td><td class="n">'.
				number_format(($aryValues['nb_uniq_visitors']*$intSum/100),2).
			'%</td></tr>';
	unset($aryTmp);
/***************************************************************************/ ?>
		</tbody>
	</table>
</div>
<?php /************************************************************************/
	include ('footer.php');

/* EOF */

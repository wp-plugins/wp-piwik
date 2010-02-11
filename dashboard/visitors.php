<?php
/*********************************
	WP-Piwik::Stats:Vistors
**********************************/

	$aryConf['data']['Visitors'] = $this->call_API(
		'VisitsSummary.getVisits', 
		$aryConf['params']['period'], 
		$aryConf['params']['date'],
		$aryConf['params']['limit']
	);
	$aryConf['data']['Unique'] = $this->call_API(
		'VisitsSummary.getUniqueVisitors',
		$aryConf['params']['period'],
		$aryConf['params']['date'],
		$aryConf['params']['limit']
	);
	$aryConf['data']['Bounced'] = $this->call_API(
		'VisitsSummary.getBounceCount',
		$aryConf['params']['period'],
		$aryConf['params']['date'],
		$aryConf['params']['limit']
	);
	$aryConf['title'] = __('Visitors', 'wp-piwik');
	include('header.php');
	$strValues = $strLabels = $strBounced =  $strValuesU = '';
	$intMax = max($aryConf['data']['Visitors']);
	while ($intMax % 10 != 0 || $intMax == 0) $intMax++;
	$intStep = $intMax / 5;
	if ($intStep < 10) $intStep = 10;
	else while ($intStep % 10 != 0 && $intStep != 1) $intStep--;
	$intUSum = 0;
	foreach ($aryConf['data']['Visitors'] as $strDate => $intValue) {
		$strValues .= round($intValue/($intMax/100),2).',';
		$strValuesU .= round($aryConf['data']['Unique'][$strDate]/($intMax/100),2).',';
		$strBounced .= round($aryConf['data']['Bounced'][$strDate]/($intMax/100),2).',';
		$strLabels .= '|'.substr($strDate,-2);
		$intUSum += $aryConf['data']['Unique'][$strDate];
	}
	$intAvg = round($intUSum/30,0);
	$intAvgG = round($intAvg/($intMax/100),2);
	$strValues = substr($strValues, 0, -1);
	$strValuesU = substr($strValuesU, 0, -1);
	$strBounced = substr($strBounced, 0, -1);
	$strBase  = 'http://chart.apis.google.com/chart?';
	$strGraph = 'cht=lc&amp;'.
		'chg=0,'.round($intStep/($intMax/100),2).',2,2&amp;'.
		'chs=500x220&amp;'.
		'chd=t:'.$strValues.'|'.$strValuesU.'|'.$strBounced.'|'.$intAvgG.','.$intAvgG.'&amp;'.
		'chxl=0:'.$strLabels.'&amp;'.
		'chco=90AAD9,A0BAE9,E9A0BA,FF0000&amp;'.
		'chm=B,D4E2ED,0,1,0|B,E4F2FD,1,2,0|B,FDE4F2,2,3,0&amp;'.
		'chxt=x,y&amp;'.
		'chxr=1,0,'.$intMax.','.$intStep;
	if (self::$bolWPMU)		
		$bolDisableGAPI = get_site_option('wpmu-piwik_disable_gapi');
	else
		$bolDisableGAPI = get_option('wp-piwik_disable_gapi');

/***************************************************************************/ ?>
<div class="wp-piwik-graph-wide">
	<?php if (!$bolDisableGAPI) { ?><img src="<?php echo $strBase.$strGraph; ?>" width="500" height="220" alt="Visits graph" /><?php } ?>
</div>
<div class="table">
	<table class="widefat wp-piwik-table">
		<thead>
			<tr>
				<th><?php _e('Date', 'wp-piwik'); ?></th>
				<th class="n"><?php _e('Visits', 'wp-piwik'); ?></th>
				<th class="n"><?php _e('Unique', 'wp-piwik'); ?></th>
				<th class="n"><?php _e('Bounced', 'wp-piwik'); ?></th>
			</tr>
		</thead>
		<tbody style="cursor:pointer;">
<?php /************************************************************************/
	$aryTmp = array_reverse($aryConf['data']['Visitors']);
	foreach ($aryTmp as $strDate => $intValue)
		echo '<tr onclick="javascript:datelink(\''.str_replace('-', '', $strDate).'\');"><td>'.$strDate.'</td><td class="n">'.
			$intValue.'</td><td class="n">'.
			$aryConf['data']['Unique'][$strDate].
			'</td><td class="n">'.
			$aryConf['data']['Bounced'][$strDate].
			'</td></tr>'."\n";
	echo '<tr><td class="n" colspan="4"><strong>'.__('Unique TOTAL', 'wp-piwik').'</strong> '.__('Sum', 'wp-piwik').': '.$intUSum.' '.__('Avg', 'wp-piwik').': '.$intAvg.'</td></tr>';	
	unset($aryTmp);
/***************************************************************************/ ?>
		</tbody>
	</table>
</div>
<?php /************************************************************************/
	include ('footer.php');

/* EOF */

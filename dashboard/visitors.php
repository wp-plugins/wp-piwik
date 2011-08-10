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
	
	if (!isset($aryConf['inline']) || $aryConf['inline'] != true)
		include('header.php');
	
	$strValues = $strLabels = $strBounced =  $strValuesU = $strCounter = '';
	$intUSum = $intCount = 0; 
	if (is_array($aryConf['data']['Visitors']))
		foreach ($aryConf['data']['Visitors'] as $strDate => $intValue) {
			$intCount++;
			$strValues .= $intValue.',';
			$strValuesU .= $aryConf['data']['Unique'][$strDate].',';
			$strBounced .= $aryConf['data']['Bounced'][$strDate].',';
			$strLabels .= '['.$intCount.',"'.substr($strDate,-2).'"],';
			$intUSum += $aryConf['data']['Unique'][$strDate];
		}
	else {$strValues = '0,'; $strLabels = '[0,"-"],'; $strValuesU = '0,'; $strBounced = '0,'; }
	$intAvg = round($intUSum/30,0);
	$strValues = substr($strValues, 0, -1);
	$strValuesU = substr($strValuesU, 0, -1);
	$strLabels = substr($strLabels, 0, -1);
	$strBounced = substr($strBounced, 0, -1);
	$strCounter = substr($strCounter, 0, -1);

/***************************************************************************/ ?>
<div class="wp-piwik-graph-wide">
	<div id="wp-piwik_stats_vistors_graph" style="height:220px;width:490px;"></div>
</div>
<?php if (!isset($aryConf['inline']) || $aryConf['inline'] != true) { ?>
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
	if (is_array($aryConf['data']['Visitors'])) {
		$aryTmp = array_reverse($aryConf['data']['Visitors']);
		foreach ($aryTmp as $strDate => $intValue)
			echo '<tr onclick="javascript:datelink(\''.urlencode('wp-piwik_stats').'\',\''.str_replace('-', '', $strDate).'\');"><td>'.$strDate.'</td><td class="n">'.
				$intValue.'</td><td class="n">'.
				$aryConf['data']['Unique'][$strDate].
				'</td><td class="n">'.
				$aryConf['data']['Bounced'][$strDate].
				'</td></tr>'."\n";
	}
	echo '<tr><td class="n" colspan="4"><strong>'.__('Unique TOTAL', 'wp-piwik').'</strong> '.__('Sum', 'wp-piwik').': '.$intUSum.' '.__('Avg', 'wp-piwik').': '.$intAvg.'</td></tr>';	
	unset($aryTmp);
/***************************************************************************/ ?>
		</tbody>
	</table>
</div>
<?php } ?>
<script type="text/javascript">
$j.jqplot('wp-piwik_stats_vistors_graph', [[<?php echo $strValues; ?>],[<?php echo $strValuesU; ?>],[<?php echo $strBounced;?>]],
{
	axes:{yaxis:{min:0, tickOptions:{formatString:'%.0f'}},xaxis:{min:1,max:30,ticks:[<?php echo $strLabels; ?>]}},
	seriesDefaults:{showMarker:false,lineWidth:1,fill:true,fillAndStroke:true,fillAlpha:0.9,trendline:{show:false,color:'#C00',lineWidth:1.5,type:'exp'}},
	series:[{color:'#90AAD9',fillColor:'#D4E2ED'},{color:'#A3BCEA',fillColor:'#E4F2FD',trendline:{show:true,label:'Unique visitor trend'}},{color:'#E9A0BA',fillColor:'#FDE4F2'}],
});
</script>
<?php /************************************************************************/
	if (!isset($aryConf['inline']) || $aryConf['inline'] != true)
		include ('footer.php');

/* EOF */

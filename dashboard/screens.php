<?php
/*********************************
	WP-Piwik::Stats:Screens
**********************************/

	$aryConf['data'] = $this->call_API(
			'UserSettings.getResolution', 
			$aryConf['params']['period'], 
			$aryConf['params']['date'],
			$aryConf['params']['limit']
	);
	$aryConf['title'] = __('Resolution', 'wp-piwik');
	include('header.php');
	$strValues = $strLabels = '';
	$intSum = 0;
	foreach ($aryConf['data'] as $aryValues)
		$intSum += $aryValues['nb_uniq_visitors'];
        $intCount = 0; $intMore = 0;
        foreach ($aryConf['data'] as $key => $aryValues) {
                $aryConf['data'][$key]['wp_piwik_percent'] = round(($aryValues['nb_uniq_visitors']/$intSum*100), 2);
                $intCount++;
                if ($intCount <= 9) {
                        $strValues .= $aryConf['data'][$key]['wp_piwik_percent'].',';
                        $strLabels .= '|'.urlencode($aryValues['label']);
                } else ($intMore += $aryConf['data'][$key]['wp_piwik_percent']);
        }
        if ($intMore) {
                $strValues .= $intMore.',';
                $strLabels .= '|'.__('Others', 'wp-piwik');
        }
	$strValues = substr($strValues, 0, -1);
	$strLabels = substr($strLabels, 1);
	$strBase  = 'http://chart.apis.google.com/chart?'.
		'cht=p&amp;'.
		'chs=500x220&amp;'.
		'chd=t:'.$strValues.'&amp;'.
		'chl='.$strLabels.'&amp;'.
		'chco=405A89,C0DAFF&amp;';
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
				<th><?php _e('Resolution', 'wp-piwik'); ?></th>
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
				number_format($aryValues['wp_piwik_percent'], 2).
			'%</td></tr>';
	unset($aryTmp);
/***************************************************************************/ ?>
		</tbody>
	</table>
</div>
<?php /************************************************************************/
	include ('footer.php');

/* EOF */

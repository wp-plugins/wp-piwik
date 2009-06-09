<?php
$aryConf['data'] = $this->call_API(
                        'UserSettings.getBrowser', 
			$aryConf['params']['period'], 
			$aryConf['params']['date'],
                        $aryConf['params']['limit']
                );
$aryConf['title'] = __('Browser');
include('header.php');
$strValues = $strLabels = '';

$intSum = 0;
foreach ($aryConf['data'] as $aryValues)
	$intSum += $aryValues['nb_uniq_visitors'];

foreach ($aryConf['data'] as $aryValues) {
        $strValues .= round(($aryValues['nb_uniq_visitors']/$intSum*100), 2).',';
        $strLabels .= '|'.urlencode($aryValues['shortLabel']);
}

$strValues = substr($strValues, 0, -1);
$strLabels = substr($strLabels, 1);

$strBase  = 'http://chart.apis.google.com/chart?';
$strGraph = 'cht=p&amp;';
$strGraph .= 'chs=500x220&amp;';
$strGraph .= 'chd=t:'.$strValues.'&amp;';
$strGraph .= 'chl='.$strLabels.'&amp;';
$strGraph .= 'chco=90AAD9,A0BAE9&amp;';
?>
<div class="wp-piwik-graph-wide">
<img src="<?php echo $strBase.$strGraph; ?>" width="500" height="220" alt="Visits graph" />
</div>
<div class="table">
<table class="widefat wp-piwik-table">
        <thead>
                <tr><th><?php _e('Browser'); ?></th><th class="n"><?php _e('Unique'); ?></th><th class="n"><?php _e('Percent'); ?></tr>
        </thead>
        <tbody>
<?php
foreach ($aryConf['data'] as $aryValues)
        echo '<tr><td>'.$aryValues['shortLabel'].'</td><td class="n">'.$aryValues['nb_uniq_visitors'].'</td><td class="n">'.number_format(($aryValues['nb_uniq_visitors']*$intSum/100),2).'%</td></tr>';
unset($aryTmp);
?>
        </tbody>
</table>
</div>
<?php include ('footer.php');

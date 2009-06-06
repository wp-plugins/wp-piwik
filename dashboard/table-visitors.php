<?php
$aryConf['data']['Visitors'] = $this->call_API(
                        'VisitsSummary.getVisits',
                        isset($aryConf['params'][0])?$aryConf['params'][0]:'',
                        isset($aryConf['params'][1])?$aryConf['params'][1]:'',
                        isset($aryConf['params'][2])?$aryConf['params'][2]:''
                );
$aryConf['data']['Unique'] = $this->call_API(
                        'VisitsSummary.getUniqueVisitors',
                        isset($aryConf['params'][0])?$aryConf['params'][0]:'',
                        isset($aryConf['params'][1])?$aryConf['params'][1]:'',
                        isset($aryConf['params'][2])?$aryConf['params'][2]:''
                );
$aryConf['title'] = __('Visitors');
include('header.php');
?>
<div class="table">
<table class="widefat">
	<thead>
		<tr><th><?php _e('Date'); ?></th><th><?php _e('Visits'); ?></th><th><?php _e('Unique'); ?></th></tr>
	</thead>
        <tbody>
<?php
$aryTmp = array_reverse($aryConf['data']['Visitors']);
foreach ($aryTmp as $strDate => $intValue)
	echo '<tr><td>'.$strDate.'</td><td>'.$intValue.'</td><td>'.$aryConf['data']['Unique'][$strDate].'</td></tr>';
unset($aryTmp);
?>
	</tbody>
</table>
</div>
<?php include('footer.php'); ?>

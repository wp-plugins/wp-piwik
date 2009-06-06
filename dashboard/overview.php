<?php 
$aryConf['data'] = $this->call_API(
			'VisitsSummary.get',
			isset($aryConf['params'][0])?$aryConf['params'][0]:'',
			isset($aryConf['params'][1])?$aryConf['params'][1]:'',
			isset($aryConf['params'][2])?$aryConf['params'][2]:''
		);
$aryConf['title'] = __('Overview');
include('header.php');
?>
<div class="table">
<table class="widefat">
<tbody>
<?php
$strTime = floor($aryConf['data']['sum_visit_length']/3600).'h '.floor(($aryConf['data']['sum_visit_length'] % 3600)/60).'m '.floor(($aryConf['data']['sum_visit_length'] % 3600) % 60).'s';
echo '<tr><td>'.__('Visitors').':</td><td>'.$aryConf['data']['nb_visits'].'</td></tr>';
echo '<tr><td>'.__('Unique visitors').':</td><td>'.$aryConf['data']['nb_uniq_visitors'].'</td></tr>';
echo '<tr><td>'.__('Page views').':</td><td>'.$aryConf['data']['nb_actions'].'</td></tr>';
echo '<tr><td>'.__('Max. page views in one visit').':</td><td>'.$aryConf['data']['max_actions'].'</td></tr>';
echo '<tr><td>'.__('Total time spent by visitors').':</td><td>'.$strTime.'</td></tr>';
echo '<tr><td>'.__('Bounce count').':</td><td>'.$aryConf['data']['bounce_count'].'</td></tr>';
echo '</tbody></table>';
?></div>
<?php include('footer.php');

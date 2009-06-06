<?php
$aryConf['data'] = $this->call_API(
                        'Referers.getKeywords',
                        isset($aryConf['params'][0])?$aryConf['params'][0]:'',
                        isset($aryConf['params'][1])?$aryConf['params'][1]:'',
                        isset($aryConf['params'][2])?$aryConf['params'][2]:''
                );
$aryConf['title'] = __('Keywords');
include('header.php');
?>
<table class="widefat">
<thead>
	<tr><th><?php _e('Keyword'); ?></th><th><?php _e('Unique'); ?></th></tr>
</thead>
<tbody>
<?php
foreach ($aryConf['data'] as $aryValues)
	echo '<tr><td>'.$aryValues['label'].'</td><td>'.$aryValues['nb_uniq_visitors'].'</td></tr>';
?>
</tbody>
</table>
<?php include('footer.php'); ?>

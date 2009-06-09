<?php
$aryConf['data'] = $this->call_API(
                        'Referers.getWebsites',
			$aryConf['params']['period'],
                        $aryConf['params']['date'],
                        $aryConf['params']['limit']
                );
$aryConf['title'] = __('Websites');
include('header.php');
?>
<table class="widefat">
<thead>
	<tr><th><?php _e('Website'); ?></th><th><?php _e('Unique'); ?></th></tr>
</thead>
<tbody>
<?php
foreach ($aryConf['data'] as $aryValues)
	echo '<tr><td>'.$aryValues['label'].'</td><td>'.$aryValues['nb_uniq_visitors'].'</td></tr>';
?>
</tbody>
</table>
<?php include('footer.php'); ?>

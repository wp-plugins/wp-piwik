<?php 
/*********************************
	WP-Piwik::Stats:SEO
**********************************/
	$aryConf['data'] = $GLOBALS['wp_piwik']->callPiwikAPI(
		'SEO.getRank',
		$aryConf['params']['period'],
		$aryConf['params']['date'],
		$aryConf['params']['limit']
	);
	$aryConf['title'] = __('SEO', 'wp-piwik');
/***************************************************************************/ ?>
<div class="table">
	<table class="widefat">
		<tbody>
			<?php foreach ($aryConf['data'] as $aryVal) { ?>
			<tr><td><?php echo $aryVal['label']; ?></td><td><?php echo $aryVal['rank']; ?></td></tr>
			<?php } ?>
		</tbody>
	</table>
</div>
<?php /************************************************************************/

/* EOF */
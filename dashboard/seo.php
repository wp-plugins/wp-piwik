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
			<tr><td><?php print_r($aryConf); ?></td></tr>
		</tbody>
	</table>
</div>
<?php /************************************************************************/

/* EOF */
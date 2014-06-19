<?php

	class WP_Piwik_Widget_Overview extends WP_Piwik_Widget {

		protected function configure() {
			$this->title = self::$settings->getGlobalOption('plugin_display_name').' - '.__('Overview', 'wp-piwik');
			$this->parameter = array(
				'period' => 'day',
				'date'  => self::$settings->getGlobalOption('dashboard_widget'),
				'limit' => null
			);
		}
		
		public function show() {
			$aryTmp = array(
				'bounce_count' => 0,
				'max_actions' => 0,
				'nb_actions' => 0,
				'nb_uniq_visitors' => 0,
				'nb_visits' => 0,
				'nb_visits_converted' => 0,
				'sum_visit_length' => 0,
				'bounce_rate' => 0,
				'nb_actions_per_visit' => 0,
				'avg_time_on_site' => 0
			);
			$response = self::$wpPiwik->request($this->apiID);
			if (!empty($response['status']) && $response['result'] ='error')
				echo '<strong>'.__('Piwik error', 'wp-piwik').':</strong> '.htmlentities($response['message'], ENT_QUOTES, 'utf-8');
			else {
				if ($this->parameter['date'] == 'last30') {
					$intValCnt = 0;
					if (is_array($response))
						foreach ($response as $aryDay) 
							foreach ($aryDay as $strKey => $strValue) {
									$intValCnt++;
									if (!in_array($strKey, array('max_actions','bounce_rate','nb_actions_per_visit','avg_time_on_site')))
										$aryTmp[$strKey] += $strValue;
									elseif ($aryTmp[$strKey] < $strValue)
									$aryTmp[$strKey] = $strValue;
							}
					$response = $aryTmp;
					if ($intValCnt > 1 && $response['nb_visits'] >0) $response['bounce_rate'] = round($response['bounce_count']/$response['nb_visits']*100).'%';
		}
		if (empty($response)) $response = $aryTmp;
/***************************************************************************/ ?>
<div class="table">
	<table class="widefat">
		<tbody>
<?php /************************************************************************/
		$strTime = 
			floor($response['sum_visit_length']/3600).'h '.
			floor(($response['sum_visit_length'] % 3600)/60).'m '.
			floor(($response['sum_visit_length'] % 3600) % 60).'s';
		$strAvgTime = 
			floor($response['avg_time_on_site']/3600).'h '.
			floor(($response['avg_time_on_site'] % 3600)/60).'m '.
			floor(($response['avg_time_on_site'] % 3600) % 60).'s';
		echo '<tr><td>'.__('Visitors', 'wp-piwik').':</td><td>'.$response['nb_visits'].'</td></tr>';
		echo '<tr><td>'.__('Unique visitors', 'wp-piwik').':</td><td>'.$response['nb_uniq_visitors'].'</td></tr>';
		echo '<tr><td>'.__('Page views', 'wp-piwik').':</td><td>'.$response['nb_actions'].' (&#216; '.$response['nb_actions_per_visit'].')</td></tr>';
		echo '<tr><td>'.__('Max. page views in one visit', 'wp-piwik').':</td><td>'.$response['max_actions'].'</td></tr>';
		echo '<tr><td>'.__('Total time spent', 'wp-piwik').':</td><td>'.$strTime.'</td></tr>';
		echo '<tr><td>'.__('Time/visit', 'wp-piwik').':</td><td>'.$strAvgTime.'</td></tr>';
		echo '<tr><td>'.__('Bounce count', 'wp-piwik').':</td><td>'.$response['bounce_count'].' ('.$response['bounce_rate'].')</td></tr>';
		if (self::$settings->getGlobalOption('piwik_shortcut')) 
			echo '<tr><td>'.__('Shortcut', 'wp-piwik').':</td><td><a href="'.self::$settings->getGlobalOption('piwik_url').'">Piwik</a>'.(isset($aryConf['inline']) && $aryConf['inline']?' - <a href="?page=wp-piwik_stats">WP-Piwik</a>':'').'</td></tr>';
/***************************************************************************/ ?>
		</tbody>
	</table>
</div>
<?php /************************************************************************/
	}
		}
		
	}
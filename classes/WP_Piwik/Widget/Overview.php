<?php

	namespace WP_Piwik\Widget;

	class Overview extends \WP_Piwik\Widget {
	
		public $className = __CLASS__;

		protected function configure() {
			$this->title = self::$settings->getGlobalOption('plugin_display_name').' - '.__('Overview', 'wp-piwik').' ('.__(self::$settings->getGlobalOption('dashboard_widget'), 'wp-piwik').')';
			$this->method = 'VisitsSummary.get';
			$this->parameter = array(
				'period' => (self::$settings->getGlobalOption('dashboard_widget')=='last30'?'range':'day'),
				'date'  => self::$settings->getGlobalOption('dashboard_widget'),
				'limit' => null
			);
		}
		
		public function show() {
			$response = self::$wpPiwik->request($this->apiID[$this->method]);
			if (!empty($response['result']) && $response['result'] ='error')
				echo '<strong>'.__('Piwik error', 'wp-piwik').':</strong> '.htmlentities($response['message'], ENT_QUOTES, 'utf-8');
			else {
				$strTime = $this->timeFormat($response['sum_visit_length']);
				$strAvgTime = $this->timeFormat($response['avg_time_on_site']);
				$tableHead = null;
				$tableBody = array(
					array(__('Visitors', 'wp-piwik').':', $response['nb_visits']),
					array(__('Unique visitors', 'wp-piwik').':', $response['nb_uniq_visitors']),
					array(__('Page views', 'wp-piwik').':', $response['nb_actions'].' (&#216; '.$response['nb_actions_per_visit'].')'),
					array(__('Max. page views in one visit', 'wp-piwik').':', $response['max_actions']),
					array(__('Total time spent', 'wp-piwik').':', $strTime),
					array(__('Time/visit', 'wp-piwik').':', $strAvgTime),
					array(__('Bounce count', 'wp-piwik').':', $response['bounce_count'].' ('.$response['bounce_rate'].')')
				); 
				$tableFoot = (self::$settings->getGlobalOption('piwik_shortcut')?array(__('Shortcut', 'wp-piwik').':', '<a href="'.self::$settings->getGlobalOption('piwik_url').'">Piwik</a>'.(isset($aryConf['inline']) && $aryConf['inline']?' - <a href="?page=wp-piwik_stats">WP-Piwik</a>':'')):null);
				$this->table($tableHead, $tableBody, $tableFoot);
			}
		}
		
	}
<?php

	namespace WP_Piwik\Widget;

	class Overview extends \WP_Piwik\Widget {
	
		public $className = __CLASS__;

		protected function configure($prefix = '') {
			$this->parameter = array(
				'idSite' => 1,
				'period' => 'range', //(self::$settings->getGlobalOption('dashboard_widget')=='last30'?'range':'day'),
				'date'  => 'last30', //self::$settings->getGlobalOption('dashboard_widget'),
				'limit' => null
			);
			$this->title = $prefix.__('Overview', 'wp-piwik').' ('.__($this->parameter['date'],'wp-piwik').')'.
				(self::$settings->getGlobalOption('piwik_shortcut')?' '.
					sprintf('<a href="%s">Piwik</a>', (self::$settings->getGlobalOption('piwik_mode') == 'pro'?
						'https://'.self::$settings->getGlobalOption('piwik_user').'.piwik.pro/':
						self::$settings->getGlobalOption('piwik_url'))
					):''
				);
			$this->method = 'VisitsSummary.get';
		}
		
		public function show() {
			$response = self::$wpPiwik->request($this->apiID[$this->method]);
			if (!empty($response['result']) && $response['result'] ='error')
				echo '<strong>'.__('Piwik error', 'wp-piwik').':</strong> '.htmlentities($response['message'], ENT_QUOTES, 'utf-8');
			else {
				$time = isset($response['sum_visit_length'])?$this->timeFormat($response['sum_visit_length']):'-';
				$avgTime = isset($response['avg_time_on_site'])?$this->timeFormat($response['avg_time_on_site']):'-';
				$tableHead = null;
				$tableBody = array(
					array(__('Visitors', 'wp-piwik').':', $this->value($response, 'nb_visits')),
					array(__('Unique visitors', 'wp-piwik').':', $this->value($response, 'nb_uniq_visitors')),
					array(__('Page views', 'wp-piwik').':', $this->value($response, 'nb_actions').' (&#216; '.$this->value($response, 'nb_actions_per_visit').')'),
					array(__('Max. page views in one visit', 'wp-piwik').':', $this->value($response, 'max_actions')),
					array(__('Total time spent', 'wp-piwik').':', $time),
					array(__('Time/visit', 'wp-piwik').':', $avgTime),
					array(__('Bounce count', 'wp-piwik').':', $this->value($response, 'bounce_count').' ('.$this->value($response, 'bounce_rate').')')
				); 
				$tableFoot = (self::$settings->getGlobalOption('piwik_shortcut')?array(__('Shortcut', 'wp-piwik').':', '<a href="'.self::$settings->getGlobalOption('piwik_url').'">Piwik</a>'.(isset($aryConf['inline']) && $aryConf['inline']?' - <a href="?page=wp-piwik_stats">WP-Piwik</a>':'')):null);
				$this->table($tableHead, $tableBody, $tableFoot);
			}
		}
		
	}
<?php

	namespace WP_Piwik\Widget;

	class Overview extends \WP_Piwik\Widget {
	
		public $className = __CLASS__;

		protected function configure($prefix = '', $params = array()) {
			$timeSettings = $this->getTimeSettings();			
			$this->parameter = array(
				'idSite' => self::$settings->getOption('site_id'),
				'period' => isset($params['period'])?$params['period']:$timeSettings['period'],
				'date'  => isset($params['date'])?$params['date']: $timeSettings['date'],
				'description' => $timeSettings['description']
			);
			$this->title = !$this->isShortcode?$prefix.__('Overview', 'wp-piwik').' ('.__($this->pageId == 'dashboard'?$this->rangeName():$timeSettings['description'],'wp-piwik').')':($params['title']?$params['title']:'');
			$this->method = 'VisitsSummary.get';
		}
		
		public function show() {
			$response = self::$wpPiwik->request($this->apiID[$this->method]);
			if (!empty($response['result']) && $response['result'] ='error')
				echo '<strong>'.__('Piwik error', 'wp-piwik').':</strong> '.htmlentities($response['message'], ENT_QUOTES, 'utf-8');
			else {
				if ($this->parameter['date'] == 'last30') {
					$result = array();
					foreach ($response as $data)
						foreach ($data as $key => $value)
							if (isset($result[$key]))
								$result[$key] += $value;
							else
								$result[$key] = $value;
					$response = $result;	
				}
				$time = isset($response['sum_visit_length'])?$this->timeFormat($response['sum_visit_length']):'-';
				$avgTime = isset($response['avg_time_on_site'])?$this->timeFormat($response['avg_time_on_site']):'-';
				$tableHead = null;
				$tableBody = array(
					array(__('Visitors', 'wp-piwik').':', $this->value($response, 'nb_visits')),
					array(__('Unique visitors', 'wp-piwik').':', $this->value($response, 'nb_uniq_visitors')),
					array(__('Page views', 'wp-piwik').':', $this->value($response, 'nb_actions').' (&#216; '.$this->value($response, 'nb_actions_per_visit').')'),
					array(__('Total time spent', 'wp-piwik').':', $time),
					array(__('Bounce count', 'wp-piwik').':', $this->value($response, 'bounce_count').' ('.$this->value($response, 'bounce_rate').')')
				);
				if ($this->parameter['date'] != 'last30')
					array_push($tableBody, array(__('Time/visit', 'wp-piwik').':', $avgTime), array(__('Max. page views in one visit', 'wp-piwik').':', $this->value($response, 'max_actions')));
				$tableFoot = (self::$settings->getGlobalOption('piwik_shortcut')?array(__('Shortcut', 'wp-piwik').':', '<a href="'.self::$settings->getGlobalOption('piwik_url').'">Piwik</a>'.(isset($aryConf['inline']) && $aryConf['inline']?' - <a href="?page=wp-piwik_stats">WP-Piwik</a>':'')):null);
				$this->table($tableHead, $tableBody, $tableFoot);
			}
		}
		
	}
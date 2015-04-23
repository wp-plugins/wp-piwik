<?php

	namespace WP_Piwik\Widget;

	class Pages extends \WP_Piwik\Widget {
	
		public $className = __CLASS__;

		protected function configure($prefix = '') {
			$timeSettings = $this->getTimeSettings();
			$this->parameter = array(
				'idSite' => self::$settings->getOption('site_id'),
				'period' => $timeSettings['period'],
				'date'  => $timeSettings['date']
			);
			$this->title = $prefix.__('Pages', 'wp-piwik').' ('.__($timeSettings['description'],'wp-piwik').')';
			$this->method = 'Actions.getPageTitles';
		}
		
		public function show() {
			$response = self::$wpPiwik->request($this->apiID[$this->method]);
			if (!empty($response['result']) && $response['result'] ='error')
				echo '<strong>'.__('Piwik error', 'wp-piwik').':</strong> '.htmlentities($response['message'], ENT_QUOTES, 'utf-8');
			else {
				//echo '<pre>'; print_r($response); echo '</pre>';
				$tableHead = array(__('Page', 'wp-piwik'), __('Unique', 'wp-piwik'), __('Visits', 'wp-piwik'));
				$tableBody = array();
				$count = 0;
				foreach ($response as $row) {
					$count++;
					$tableBody[] = array($row['label'], $row['nb_uniq_visitors'], $row['nb_visits']);
					if ($count == 10) break;
				}
				$this->table($tableHead, $tableBody, null);
			}
		}
		
	}
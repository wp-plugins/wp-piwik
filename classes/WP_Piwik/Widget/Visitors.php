<?php

	namespace WP_Piwik\Widget;

	class Visitors extends \WP_Piwik\Widget {
	
		public $className = __CLASS__;

		protected function configure($prefix = '', $params = array()) {
			$this->parameter = array(
				'idSite' => self::$settings->getOption('site_id'),
				'period' => 'day',
				'date'  => 'last30',
				'limit' => null
			);
			$this->title = $prefix.__('Visitors', 'wp-piwik').' ('.__($this->parameter['date'],'wp-piwik').')';
			$this->method = array('VisitsSummary.getVisits', 'VisitsSummary.getUniqueVisitors', 'VisitsSummary.getBounceCount', 'VisitsSummary.getActions');
			$this->context = 'normal';
			wp_enqueue_script('wp-piwik', self::$wpPiwik->getPluginURL().'js/wp-piwik.js', array(), self::$wpPiwik->getPluginVersion(), true);
			wp_enqueue_script('wp-piwik-jqplot',self::$wpPiwik->getPluginURL().'js/jqplot/wp-piwik.jqplot.js',array('jquery'));
			wp_enqueue_style('wp-piwik', self::$wpPiwik->getPluginURL().'css/wp-piwik.css',array(),self::$wpPiwik->getPluginVersion());
			add_action('admin_head-index.php', array($this, 'addHeaderLines'));
		}
		
		public function show() {
			$response = array();
			$success = true;
			foreach ($this->method as $method) {
				$response[$method] = self::$wpPiwik->request($this->apiID[$method]);
				if (!empty($response[$method]['result']) && $response[$method]['result'] ='error')
					$success = false;
			}
			if (!$success)
				echo '<strong>'.__('Piwik error', 'wp-piwik').':</strong> '.htmlentities($response[$method]['message'], ENT_QUOTES, 'utf-8');
			else {
				$data = array();
				foreach ($response['VisitsSummary.getVisits'] as $key => $value)
					$data[] = array(
						$key, 
						$value, 
						$response['VisitsSummary.getUniqueVisitors'][$key]?$response['VisitsSummary.getUniqueVisitors'][$key]:'-',
						$response['VisitsSummary.getBounceCount'][$key]?$response['VisitsSummary.getBounceCount'][$key]:'-',
						$response['VisitsSummary.getActions'][$key]?$response['VisitsSummary.getActions'][$key]:'-'
					);
				$this->table(
					array(__('Date', 'wp-piwik'), __('Visits', 'wp-piwik'), __('Unique', 'wp-piwik'), __('Bounced', 'wp-piwik'), __('Page Views', 'wp-piwik')),
					array_reverse($data)
				);
			}
			
		}
		
	}
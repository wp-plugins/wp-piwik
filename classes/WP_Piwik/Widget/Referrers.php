<?php

	namespace WP_Piwik\Widget;

	class Referrers extends \WP_Piwik\Widget {
	
		public $className = __CLASS__;

		protected function configure($prefix = '', $params = array()) {
			$timeSettings = $this->getTimeSettings();
			$this->parameter = array(
				'idSite' => self::$settings->getOption('site_id'),
				'period' => $timeSettings['period'],
				'date'  => $timeSettings['date']
			);
			$this->title = $prefix.__('Referrers', 'wp-piwik').' ('.__($timeSettings['description'],'wp-piwik').')';
			$this->method = 'Referrers.getWebsites';
			$this->name = 'Referrer';
		}
		
	}
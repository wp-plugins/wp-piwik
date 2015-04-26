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
			$this->name = 'Page';
		}
		
	}
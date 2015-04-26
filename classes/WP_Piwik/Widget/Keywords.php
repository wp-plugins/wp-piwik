<?php

	namespace WP_Piwik\Widget;

	class Keywords extends \WP_Piwik\Widget {
	
		public $className = __CLASS__;

		protected function configure($prefix = '') {
			$timeSettings = $this->getTimeSettings();
			$this->parameter = array(
				'idSite' => self::$settings->getOption('site_id'),
				'period' => $timeSettings['period'],
				'date'  => $timeSettings['date']
			);
			$this->title = $prefix.__('Keywords', 'wp-piwik').' ('.__($timeSettings['description'],'wp-piwik').')';
			$this->method = 'Referrers.getKeywords';
			$this->name = 'Keyword';
		}

	}
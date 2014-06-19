<?php

	abstract class WP_Piwik_Widget {
		
		protected static $wpPiwik, $settings;
		
		protected $title = '', $type = 'dashboard', $context = 'side', $priority = 'high', $parameter = array(), $apiID = null;
		
		public function __construct($wpPiwik, $settings) {
			self::$wpPiwik = $wpPiwik;
			self::$settings = $settings;
			$this->configure();
			$this->apiID = WP_Piwik_Request::register('VisitsSummary.get', $this->parameter);
			add_meta_box(
				__CLASS__,
				$this->title, 
				array($this, 'show'), 
				$this->type, 
				$this->context, 
				$this->priority
			);
		}
		
		protected function configure() {}
		
		abstract function show();
		
	}
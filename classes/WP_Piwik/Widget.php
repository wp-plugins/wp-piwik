<?php

	namespace WP_Piwik;

	abstract class Widget {
		
		protected static $wpPiwik, $settings;
		
		protected $method = '', $title = '', $context = 'side', $priority = 'high', $parameter = array(), $apiID = array(), $pageId = 'dashboard';
		
		public function __construct($wpPiwik, $settings, $pageId = 'dashboard') {
			self::$wpPiwik = $wpPiwik;
			self::$settings = $settings;
			$this->pageId = $pageId;
			$prefix = ($this->pageId=='dashboard'?self::$settings->getGlobalOption('plugin_display_name').' - ':'');
			$this->configure($prefix);
			if (is_array($this->method)) 
				foreach ($this->method as $method) {
					$this->apiID[$method] = \WP_Piwik\Request::register($method, $this->parameter);
					self::$wpPiwik->log("Register request: ".$this->apiID[$method]);
				}
			else {
				$this->apiID[$this->method] = \WP_Piwik\Request::register($this->method, $this->parameter);
				self::$wpPiwik->log("Register request: ".$this->apiID[$this->method]);
			}
			add_meta_box(
				$this->getClass(),
				$this->title,
				array($this, 'show'), 
				$pageId,
				$this->context, 
				$this->priority
			);
		}
		
		protected function configure($prefix = '') {}
		
		abstract function show();
		
		protected function getClass() {
			return $this->className;
		}
		
		protected function timeFormat($time) {
			return floor($time/3600).'h '.floor(($time % 3600)/60).'m '.floor(($time % 3600)%60).'s';
		}
		
		protected function table($thead, $tbody = array(), $tfoot = array()) {
			echo '<div class="table"><table class="widefat">';
			if (!empty($thead)) $this->tabHead($thead);
			if (!empty($tbody)) $this->tabBody($tbody);
			if (!empty($tfoot)) $this->tabFoot($tfoot);
			echo '</table></div>';
		}

		private function tabHead($thead) {
			echo '<thead><tr>';
			foreach ($thead as $value)
				echo '<th>'.$value.'</th>';
			echo '</tr></thead>';
		}
		
		private function tabBody($tbody) {
			echo '<tbody>';
			foreach ($tbody as $trow)
				$this->tabRow($trow);
			echo '</tbody>';
		}
		
		private function tabFoot($tfoot) {
			echo '<tfoot><tr>';
			foreach ($tfoot as $value)
				echo '<td>'.$value.'</td>';
			echo '</tr></tfoot>';
		}
				
		private function tabRow($trow) {
			echo '<tr>';
			foreach ($trow as $tcell)
				echo '<td>'.$tcell.'</td>';
			echo '</tr>';
		}
		
		protected function value($array, $key) {
			return (isset($array[$key])?$array[$key]:'-');
		}
		
	}
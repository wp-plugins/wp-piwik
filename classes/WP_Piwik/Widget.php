<?php

	abstract class WP_Piwik_Widget {
		
		protected static $wpPiwik, $settings;
		
		protected $method = '', $title = '', $type = 'dashboard', $context = 'side', $priority = 'high', $parameter = array(), $apiID = null;
		
		public function __construct($wpPiwik, $settings) {
			self::$wpPiwik = $wpPiwik;
			self::$settings = $settings;
			$this->configure();
			$this->apiID = WP_Piwik_Request::register($this->method, $this->parameter);
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
		
		protected function timeFormat($time) {
			return floor($time/3600).'h '.floor(($time % 3600)/60).'m '.floor(($time % 3600)%60).'s';
		}
		
		protected function table($thead, $tbody, $tfoot) {
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
				$this->tabRow($trow[0], $trow[1]);
			echo '</tbody>';
		}
		
		private function tabFoot($tfoot) {
			echo '<tfoot><tr>';
			foreach ($tfoot as $value)
				echo '<td>'.$value.'</td>';
			echo '</tr></tfoot>';
		}
				
		private function tabRow($name, $value) {
			echo '<tr><td>'.$name.'</td><td>'.$value.'</td></tr>';
		}
		
	}
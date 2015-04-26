<?php

	namespace WP_Piwik;

	abstract class Widget {
		
		protected static $wpPiwik, $settings;
		
		protected $isShortcode = false, $method = '', $title = '', $context = 'side', $priority = 'core', $parameter = array(), $apiID = array(), $pageId = 'dashboard', $name = 'Value', $limit = 10;
		
		public function __construct($wpPiwik, $settings, $pageId = 'dashboard', $context = 'side', $priority = 'default', $params = array(), $isShortcode = false) {
			self::$wpPiwik = $wpPiwik;
			self::$settings = $settings;
			$this->pageId = $pageId;
			$this->context = $context;
			$this->priority = $priority;
			$this->isShortcode = $isShortcode;
			$prefix = ($this->pageId=='dashboard'?self::$settings->getGlobalOption('plugin_display_name').' - ':'');
			$this->configure($prefix, $params);
			if (is_array($this->method)) 
				foreach ($this->method as $method) {
					$this->apiID[$method] = \WP_Piwik\Request::register($method, $this->parameter);
					self::$wpPiwik->log("Register request: ".$this->apiID[$method]);
				}
			else {
				$this->apiID[$this->method] = \WP_Piwik\Request::register($this->method, $this->parameter);
				self::$wpPiwik->log("Register request: ".$this->apiID[$this->method]);
			}
			if ($this->isShortcode)
				return;
			add_meta_box(
				$this->getName(),
				$this->title,
				array($this, 'show'), 
				$pageId,
				$this->context, 
				$this->priority
			);
		}
		
		protected function configure($prefix = '', $params = array()) {}
		
		public function show() {
			$response = self::$wpPiwik->request($this->apiID[$this->method]);
			if (!empty($response['result']) && $response['result'] ='error')
				echo '<strong>'.__('Piwik error', 'wp-piwik').':</strong> '.htmlentities($response['message'], ENT_QUOTES, 'utf-8');
			else {
				if (isset($response[0]['nb_uniq_visitors'])) $unique = 'nb_uniq_visitors';
				else $unique = 'sum_daily_nb_uniq_visitors';
				$tableHead = array('label' => __($this->name, 'wp-piwik'));
				$tableHead[$unique] = __('Unique', 'wp-piwik');
				if (isset($response[0]['nb_visits']))
					$tableHead['nb_visits'] = __('Visits', 'wp-piwik');
				if (isset($response[0]['nb_hits']))
					$tableHead['nb_hits'] = __('Hits', 'wp-piwik');
				if (isset($response[0]['nb_actions']))
					$tableHead['nb_actions'] = __('Actions', 'wp-piwik');
				$tableBody = array();
				$count = 0;
				foreach ($response as $rowKey => $row) {
					$count++;
					$tableBody[$rowKey] = array();
					foreach ($tableHead as $key => $value)
						$tableBody[$rowKey][] = isset($row[$key])?$row[$key]:'-';
					if ($count == 10) break;
				}
				$this->table($tableHead, $tableBody, null);
			}			
		}
		
		protected function timeFormat($time) {
			return floor($time/3600).'h '.floor(($time % 3600)/60).'m '.floor(($time % 3600)%60).'s';
		}
		
		protected function table($thead, $tbody = array(), $tfoot = array()) {
			echo '<div class="table"><table class="widefat wp-piwik-table">';
			if ($this->isShortcode && $this->title)
				echo '<tr><th colspan="10">'.$this->title.'</th></tr>';
			if (!empty($thead)) $this->tabHead($thead);
			if (!empty($tbody)) $this->tabBody($tbody);
			if (!empty($tfoot)) $this->tabFoot($tfoot);
			echo '</table></div>';
		}

		private function tabHead($thead) {
			echo '<thead><tr>';
			$count = 0;
			foreach ($thead as $value)
				echo '<th'.($count++?' style="text-align:right"':'').'>'.$value.'</th>';
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
			$count = 0;
			foreach ($tfoot as $value)
				echo '<td'.($count++?' style="text-align:right"':'').'>'.$value.'</td>';
			echo '</tr></tfoot>';
		}
				
		private function tabRow($trow) {
			echo '<tr>';
			$count = 0;
			foreach ($trow as $tcell)
				echo '<td'.($count++?' style="text-align:right"':'').'>'.$tcell.'</td>';
			echo '</tr>';
		}
		
		protected function getTimeSettings() {
			switch (self::$settings->getGlobalOption('default_date')) {
				case 'today':
					$period = 'day';
					$date = 'today';
					$description = 'today';
				break;
				case 'current_month':
					$period = 'month';
					$date = 'today';
					$description = 'current month';
				break;
				case 'last_month':
					$period = 'month';
					$date = date("Y-m-d", strtotime("last day of previous month"));
					$description = 'last month';
				break;
				case 'current_week':
					$period = 'week';
					$date = 'today';
					$description = 'current week';
				break;
				case 'last_week':
					$period = 'week';
					$date = date("Y-m-d", strtotime("-1 week"));
					$description = 'last week';
				break;
				case 'yesterday':
					$period = 'day';
					$date = 'yesterday';
					$description = 'yesterday';					
				break;
				default:
				break;
			}
			return array('period' => $period, 'date' => $date, 'description' => $description);
		}

		public function getName() {
			return str_replace('\\', '-', get_called_class());;
		}
		
		public function pieChart($data) {
			echo '<div id="wp-piwik_stats_'.$this->getName().'_graph" style="height:310px;width:100%"></div>';
			echo '<script type="text/javascript">$plotBrowsers = $j.jqplot("wp-piwik_stats_'.$this->getName().'_graph", [[';
			$list = '';
			foreach ($data as $dataSet)
				$list .= '["'.$dataSet[0].'", '.$dataSet[1].'],';
			echo substr($list, 0, -1);
			echo ']], {seriesDefaults:{renderer:$j.jqplot.PieRenderer, rendererOptions:{sliceMargin:8}},legend:{show:true}});</script>';
		}
		
		protected function value($array, $key) {
			return (isset($array[$key])?$array[$key]:'-');
		}
		
	}
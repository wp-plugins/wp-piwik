<?php

	namespace WP_Piwik\Widget;

	class Systems extends \WP_Piwik\Widget {
	
		protected function configure($prefix = '', $params = array()) {
			$timeSettings = $this->getTimeSettings();			
			$this->parameter = array(
				'idSite' => self::$settings->getOption('site_id'),
				'period' => $timeSettings['period'],
				'date'  => $timeSettings['date']
			);
			$this->title = $prefix.__('Operation Systems', 'wp-piwik').' ('.__($timeSettings['description'],'wp-piwik').')';
			$this->method = 'UserSettings.getOS';
			$this->context = 'normal';
			wp_enqueue_script('wp-piwik', self::$wpPiwik->getPluginURL().'js/wp-piwik.js', array(), self::$wpPiwik->getPluginVersion(), true);
			wp_enqueue_script('wp-piwik-jqplot',self::$wpPiwik->getPluginURL().'js/jqplot/wp-piwik.jqplot.js',array('jquery'));
			wp_enqueue_style('wp-piwik', self::$wpPiwik->getPluginURL().'css/wp-piwik.css',array(),self::$wpPiwik->getPluginVersion());
			add_action('admin_head-index.php', array($this, 'addHeaderLines'));
		}
		
		public function addHeaderLines() {
			echo '<!--[if IE]><script language="javascript" type="text/javascript" src="'.self::$wpPiwik->getPluginURL().'js/jqplot/excanvas.min.js"></script><![endif]-->';
			echo '<link rel="stylesheet" href="'.self::$wpPiwik->getPluginURL().'js/jqplot/jquery.jqplot.min.css" type="text/css"/>';
			echo '<script type="text/javascript">var $j = jQuery.noConflict();</script>';			
		}
		
		public function show() {
			$response = self::$wpPiwik->request($this->apiID[$this->method]);
			$tableBody = array();
			if (!empty($response['result']) && $response['result'] ='error')
				echo '<strong>'.__('Piwik error', 'wp-piwik').':</strong> '.htmlentities($response['message'], ENT_QUOTES, 'utf-8');
			else {
				$tableHead = array(__('Operation System', 'wp-piwik'), __('Unique', 'wp-piwik'), __('Percent', 'wp-piwik'));
				if (isset($response[0]['nb_uniq_visitors'])) $unique = 'nb_uniq_visitors';
				else $unique = 'sum_daily_nb_uniq_visitors';
				$count = 0;
				$sum = 0;
				foreach ($response as $row) {
					$count++;
					$sum += isset($row[$unique])?$row[$unique]:0;
					if ($count < $this->limit)
						$tableBody[$row['label']] = array($row['label'], $row[$unique], 0);
					elseif (!isset($tableBody['Others']))
						$tableBody['Others'] = array($row['label'], $row[$unique], 0);
					else 
						$tableBody['Others'][1] += $row[$unique];
				}
				if ($count > $this->limit)
					$tableBody['Others'][0] = __('Others', 'wp-piwik');

				foreach ($tableBody as $key => $row)
					$tableBody[$key][2] = number_format($row[1]/$sum*100, 2).'%';
				
				if (!empty($tableBody)) $this->pieChart($tableBody);
				$this->table($tableHead, $tableBody, null);
			}
		}
				
	}
<?php

	namespace WP_Piwik\Widget;

	class Seo extends \WP_Piwik\Widget {
	
		public $className = __CLASS__;

		protected function configure() {
			$this->title = self::$settings->getGlobalOption('plugin_display_name').' - '.__('SEO', 'wp-piwik').' ('.__(self::$settings->getGlobalOption('dashboard_widget'), 'wp-piwik').')';
			/*$this->method = 'SEO.getRank';
			$this->parameter = array(
				'period' => (self::$settings->getGlobalOption('dashboard_widget')=='last30'?'range':'day'),
				'date'  => self::$settings->getGlobalOption('dashboard_widget'),
				'limit' => 0,
				'expanded' => 0,
				'url' => 'https://www.braekling.de'
			);*/
		}
		
		public function show() {
			$response = null; //self::$wpPiwik->request($this->apiID[$this->method]);
			if (!empty($response['result']) && $response['result'] ='error')
				echo '<strong>'.__('Piwik error', 'wp-piwik').':</strong> '.htmlentities($response['message'], ENT_QUOTES, 'utf-8');
			else {
				echo '<div class="table"><table class="widefat"><tbody>';
				if (is_array($response))
					foreach ($response as $val)
						echo '<tr><td>'.$val[0].'</td><td>'.$val[1].'</td></tr>';
				else echo '<tr><td>SEO module currently not available.</td></tr>';
				echo '</tbody></table></div>';
			}
		}
		
	}